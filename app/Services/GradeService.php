<?php

namespace App\Services;

use App\Models\AssessmentCategory;
use App\Models\GradeScaleBand;
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
     * Weighted result = sum(category average % * category weight) / sum(weight of categories with at least one grade).
     * Categories with no scores yet are excluded from the denominator rather than counted as zero, so a
     * partially-graded gradebook doesn't unfairly tank the running total.
     */
    public function computeStudentResult(Student $student, Section $section, Subject $subject, Term $term): array
    {
        $categories = AssessmentCategory::where('section_id', $section->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->with('assessments.grades')
            ->get();

        $weightedSum = 0;
        $weightUsed = 0;
        $breakdown = [];

        foreach ($categories as $category) {
            $scores = [];
            foreach ($category->assessments as $assessment) {
                $grade = $assessment->grades->firstWhere('student_id', $student->id);
                if ($grade && $assessment->max_score > 0) {
                    $scores[] = (float) $grade->score / (float) $assessment->max_score * 100;
                }
            }

            $categoryAvg = count($scores) ? array_sum($scores) / count($scores) : null;
            $breakdown[$category->id] = [
                'name' => $category->name,
                'weight' => $category->weight_pct,
                'avg' => $categoryAvg,
            ];

            if ($categoryAvg !== null) {
                $weightedSum += $categoryAvg * (float) $category->weight_pct;
                $weightUsed += (float) $category->weight_pct;
            }
        }

        $pct = $weightUsed > 0 ? round($weightedSum / $weightUsed, 2) : null;
        $band = $pct !== null ? $this->bandFor($student, $pct) : null;

        return [
            'pct' => $pct,
            'letter' => $band?->letter,
            'gpa' => $band?->gpa_point,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Every student's subject marks in a section for a term, with the same formula as
     * computeStudentResult() but in exact decimals: a category is the mean of its graded items'
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

    protected function bandFor(Student $student, float $pct): ?GradeScaleBand
    {
        return GradeScaleBand::where('department_id', $student->department_id)
            ->where('min_score', '<=', $pct)
            ->orderByDesc('min_score')
            ->first();
    }
}
