<?php

namespace App\Services;

use App\Models\AssessmentCategory;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class GradeService
{
    /** Decimal places kept in intermediate steps before the final two-decimal mark. */
    private const SCALE = 10;

    /**
     * One student's gradebook categories per subject for a term, for the breakdown on the grade
     * pages: each category's name, weight and average percentage (the mean of its graded items),
     * or null when nothing in it is graded yet.
     *
     * @return array<int, list<array{name: string, weight: string, avg: ?string}>> subject id => categories
     */
    public function categoryBreakdown(Section $section, Term $term, Student $student): array
    {
        $breakdown = [];
        $categories = AssessmentCategory::where('section_id', $section->id)->where('term_id', $term->id)
            ->with(['assessments.grades' => fn ($q) => $q->where('student_id', $student->id)])->orderBy('id')->get();

        foreach ($categories as $category) {
            $percentages = [];
            foreach ($category->assessments as $assessment) {
                $grade = $assessment->grades->first();
                $max = BigDecimal::of((string) $assessment->max_score);
                if ($grade && $max->isGreaterThan(0)) {
                    $percentages[] = BigDecimal::of((string) $grade->score)->multipliedBy(100)->dividedBy($max, self::SCALE, RoundingMode::HALF_UP);
                }
            }
            $average = $percentages === [] ? null : (string) array_reduce($percentages, fn (BigDecimal $sum, BigDecimal $p) => $sum->plus($p), BigDecimal::zero())
                ->dividedBy(count($percentages), 0, RoundingMode::HALF_UP);

            $breakdown[$category->subject_id][] = ['name' => $category->name, 'weight' => (string) $category->weight_pct, 'avg' => $average];
        }

        return $breakdown;
    }

    /**
     * Every student's subject marks in a section for a term, in exact decimals: a category is the
     * mean of its graded items'
     * percentages, and the mark is the weighted mean of the categories that have a grade. A subject
     * with no graded item has no mark. Marks are rounded to two decimals, half up.
     *
     * @return array<int, array<int, string>> student id => subject id => mark, e.g. "82.25"
     */
    public function sectionMarks(Section $section, Term $term): array
    {
        $categories = AssessmentCategory::where('section_id', $section->id)
            ->where('term_id', $term->id)
            ->with('assessments.grades')
            ->get()
            ->groupBy('subject_id');

        $marks = [];
        foreach ($categories as $subjectId => $subjectCategories) {
            /** @var array<int, array{sum: BigDecimal, weight: BigDecimal}> $totals */
            $totals = [];
            foreach ($subjectCategories as $category) {
                $weight = BigDecimal::of((string) $category->weight_pct);
                $perStudent = [];
                foreach ($category->assessments as $assessment) {
                    $max = BigDecimal::of((string) $assessment->max_score);
                    if ($max->isLessThanOrEqualTo(0)) {
                        continue;
                    }
                    foreach ($assessment->grades as $grade) {
                        $perStudent[$grade->student_id][] = BigDecimal::of((string) $grade->score)
                            ->multipliedBy(100)->dividedBy($max, self::SCALE, RoundingMode::HALF_UP);
                    }
                }
                foreach ($perStudent as $studentId => $percentages) {
                    $average = array_reduce($percentages, fn (BigDecimal $sum, BigDecimal $p) => $sum->plus($p), BigDecimal::zero())
                        ->dividedBy(count($percentages), self::SCALE, RoundingMode::HALF_UP);
                    $totals[$studentId] ??= ['sum' => BigDecimal::zero(), 'weight' => BigDecimal::zero()];
                    $totals[$studentId]['sum'] = $totals[$studentId]['sum']->plus($average->multipliedBy($weight));
                    $totals[$studentId]['weight'] = $totals[$studentId]['weight']->plus($weight);
                }
            }
            foreach ($totals as $studentId => $t) {
                if ($t['weight']->isGreaterThan(0)) {
                    $marks[$studentId][$subjectId] = (string) $t['sum']->dividedBy($t['weight'], 2, RoundingMode::HALF_UP);
                }
            }
        }

        return $marks;
    }

    public function totalWeight(Section $section, Subject $subject, Term $term): float
    {
        return (float) AssessmentCategory::where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->sum('weight_pct');
    }
}
