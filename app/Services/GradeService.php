<?php

namespace App\Services;

use App\Models\AssessmentCategory;
use App\Models\GradeScaleBand;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

class GradeService
{
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
