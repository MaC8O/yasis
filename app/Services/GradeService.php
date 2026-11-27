<?php

namespace App\Services;

use App\Models\AssessmentCategory;
use App\Models\GradebookTemplateCategory;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Gradebook arithmetic, in exact decimals. How a category scores depends on its kind:
 * - a template category with items (KG1 to Grade 10): total marks earned / total marks possible,
 *   over the items the student has a mark for (§4.1);
 * - the template's Attendance category: from the attendance records (AttendanceScoreService);
 * - a teacher's own category (Grades 11-12): the mean of its graded items' percentages, as before.
 * The subject mark is the weighted mean of the categories that have a score; a category with
 * nothing graded is left out while the term is open. A subject has no mark until at least one of
 * its items is graded (attendance alone, being the same for every subject, doesn't make a mark).
 * Archived categories and archived items never count.
 */
class GradeService
{
    /** Decimal places kept in intermediate steps before the final two-decimal mark. */
    private const SCALE = 10;

    public function __construct(private AttendanceScoreService $attendance) {}

    /**
     * Every student's subject marks in a section for a term, rounded to two decimals, half up.
     *
     * @return array<int, array<int, string>> student id => subject id => mark, e.g. "82.25"
     */
    public function sectionMarks(Section $section, Term $term): array
    {
        $marks = [];
        foreach ($this->scores($section, $term) as $subjectId => $categories) {
            $totals = [];
            foreach ($categories as $category) {
                $weight = BigDecimal::of((string) $category['weight']);
                foreach ($category['students'] as $studentId => $score) {
                    $totals[$studentId] ??= ['sum' => BigDecimal::zero(), 'weight' => BigDecimal::zero(), 'items' => false];
                    $totals[$studentId]['sum'] = $totals[$studentId]['sum']->plus($score->multipliedBy($weight));
                    $totals[$studentId]['weight'] = $totals[$studentId]['weight']->plus($weight);
                    $totals[$studentId]['items'] = $totals[$studentId]['items'] || $category['kind'] !== 'attendance';
                }
            }
            foreach ($totals as $studentId => $t) {
                if ($t['items'] && $t['weight']->isGreaterThan(0)) {
                    $marks[$studentId][$subjectId] = (string) $t['sum']->dividedBy($t['weight'], 2, RoundingMode::HALF_UP);
                }
            }
        }

        return $marks;
    }

    /**
     * One student's categories per subject for the breakdown on the grade pages: name, weight,
     * score as a whole percentage (or null when nothing is graded) and, for Attendance, the days.
     *
     * @return array<int, list<array{name: string, weight: string, avg: ?string, detail: ?string}>> subject id => categories
     */
    public function categoryBreakdown(Section $section, Term $term, Student $student): array
    {
        $breakdown = [];
        $attendanceRow = null;
        foreach ($this->scores($section, $term) as $subjectId => $categories) {
            foreach ($categories as $category) {
                $score = $category['students'][$student->id] ?? null;
                if ($category['kind'] === 'attendance') {
                    $attendanceRow ??= $this->attendance->forSectionTerm($section, $term)['students'][$student->id] ?? null;
                }
                $breakdown[$subjectId][] = [
                    'name' => $category['name'],
                    'weight' => (string) $category['weight'],
                    'avg' => $score === null ? null : (string) $score->toScale(0, RoundingMode::HALF_UP),
                    'detail' => $category['kind'] === 'attendance' ? AttendanceScoreService::describe($attendanceRow) : null,
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Subjects whose gradebook is on the template but whose Examinations category has no items.
     * When a term is locked, such a subject has no final mark (owner's decision, phase 5).
     *
     * @return list<int> subject ids
     */
    public function subjectsWithoutExaminations(Section $section, Term $term): array
    {
        return AssessmentCategory::where('section_id', $section->id)->where('term_id', $term->id)
            ->active()->whereNotNull('template_key')->withCount('activeAssessments')->get()
            ->groupBy('subject_id')
            ->filter(fn (Collection $categories) => ($categories->firstWhere('template_key', GradebookTemplateCategory::EXAMINATIONS)?->active_assessments_count ?? 0) === 0)
            ->keys()->map(fn ($id) => (int) $id)->values()->all();
    }

    public function totalWeight(Section $section, Subject $subject, Term $term): float
    {
        return (float) AssessmentCategory::where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->active()
            ->sum('weight_pct');
    }

    /**
     * Each active category's score per student, as a percentage in exact decimals.
     *
     * @return array<int, list<array{name: string, weight: string, kind: string, students: array<int, BigDecimal>}>> subject id => categories
     */
    private function scores(Section $section, Term $term): array
    {
        $categories = AssessmentCategory::where('section_id', $section->id)->where('term_id', $term->id)
            ->active()->with('activeAssessments.grades')->orderBy('id')->get();
        $attendance = null;

        $scores = [];
        foreach ($categories as $category) {
            $kind = match (true) {
                $category->isAttendance() => 'attendance',
                $category->isTemplate() => 'earned_over_possible',
                default => 'mean_of_items',
            };
            $students = [];

            if ($kind === 'attendance') {
                $attendance ??= $this->attendance->forSectionTerm($section, $term);
                foreach ($attendance['students'] as $studentId => $row) {
                    if ($row['pct'] !== null) {
                        $students[$studentId] = BigDecimal::of($row['pct']);
                    }
                }
            } else {
                $earned = $possible = $percentages = [];
                foreach ($category->activeAssessments as $assessment) {
                    $max = BigDecimal::of((string) $assessment->max_score);
                    if ($max->isLessThanOrEqualTo(0)) {
                        continue;
                    }
                    foreach ($assessment->grades as $grade) {
                        $score = BigDecimal::of((string) $grade->score);
                        $earned[$grade->student_id] = ($earned[$grade->student_id] ?? BigDecimal::zero())->plus($score);
                        $possible[$grade->student_id] = ($possible[$grade->student_id] ?? BigDecimal::zero())->plus($max);
                        $percentages[$grade->student_id][] = $score->multipliedBy(100)->dividedBy($max, self::SCALE, RoundingMode::HALF_UP);
                    }
                }
                foreach ($possible as $studentId => $total) {
                    $students[$studentId] = $kind === 'earned_over_possible'
                        ? $earned[$studentId]->multipliedBy(100)->dividedBy($total, self::SCALE, RoundingMode::HALF_UP)
                        : array_reduce($percentages[$studentId], fn (BigDecimal $sum, BigDecimal $p) => $sum->plus($p), BigDecimal::zero())
                            ->dividedBy(count($percentages[$studentId]), self::SCALE, RoundingMode::HALF_UP);
                }
            }

            $scores[$category->subject_id][] = ['name' => $category->name, 'weight' => (string) $category->weight_pct, 'kind' => $kind, 'students' => $students];
        }

        return $scores;
    }
}
