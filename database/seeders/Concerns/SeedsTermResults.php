<?php

namespace Database\Seeders\Concerns;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\GradebookTemplateCategory;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\SubjectOffering;
use App\Models\SubjectStatus;
use App\Models\TeachingAssignment;
use App\Models\Term;

/**
 * A section's complete gradebook and daily attendance for one term, so its results can be
 * calculated: the fixed template's categories (KG1 to Grade 10) or the teacher's own (11-12),
 * items for every subject, and a mark for every student. Marks are deterministic, mostly 55-98%
 * with an occasional fail; $salt varies them between terms.
 */
trait SeedsTermResults
{
    private const ITEMS = [
        'quizzes' => [['Quiz 1', 10, 'quiz'], ['Quiz 2', 20, 'quiz']],
        'assignments' => [['Homework 1', 20, 'assignment'], ['Project', 30, 'assignment']],
        'participation' => [['Class participation', 10, 'classwork']],
        'examinations' => [['Mid-term exam', 50, 'test'], ['Final exam', 100, 'test']],
    ];

    private const OWN_CATEGORIES = [
        ['Coursework', 40, [['Assignment 1', 20, 'assignment'], ['Lab report', 30, 'assignment']]],
        ['Examinations', 60, [['Mid-term exam', 50, 'test'], ['Final exam', 100, 'test']]],
    ];

    protected function seedGradebook(Section $section, Term $term, $students, string $salt = ''): void
    {
        $fallbackTeacher = StaffProfile::where('staff_id_number', 'USR-0005')->value('id');
        $usesTemplate = (bool) $section->gradeLevel?->gradingScheme?->uses_gradebook_template;
        $template = GradebookTemplateCategory::ordered()->get();

        foreach (SubjectOffering::where('grade_level_id', $section->grade_level_id)->active()->get() as $offering) {
            $teacher = TeachingAssignment::where('section_id', $section->id)->where('subject_id', $offering->subject_id)->value('teacher_id')
                ?? $section->homeroom_teacher_id ?? $fallbackTeacher;
            $base = ['section_id' => $section->id, 'subject_id' => $offering->subject_id, 'term_id' => $term->id];

            // SUPW: a word, not a mark (Q5). Mostly Pass; about one student in seven Incomplete.
            if ($offering->usesCompletion()) {
                $userId = $teacher; // a staff profile's id is its user's id
                foreach ($students as $studentId) {
                    SubjectStatus::firstOrCreate(
                        ['student_id' => $studentId, 'subject_id' => $offering->subject_id, 'term_id' => $term->id],
                        ['section_id' => $section->id, 'status' => crc32("{$studentId}-supw{$salt}") % 7 === 0 ? 'Incomplete' : 'Pass', 'recorded_by' => $userId],
                    );
                }

                continue;
            }

            if ($usesTemplate) {
                // Created directly, as the template defines them (the term may already be locked).
                foreach ($template as $t) {
                    $category = AssessmentCategory::firstOrCreate($base + ['template_key' => $t->key], ['name' => $t->name, 'weight_pct' => $t->weight_pct]);
                    $this->seedItems($category, self::ITEMS[$t->key] ?? [], $students, $teacher, $salt);
                }
            } else {
                foreach (self::OWN_CATEGORIES as [$categoryName, $weight, $categoryItems]) {
                    $category = AssessmentCategory::firstOrCreate($base + ['name' => $categoryName], ['weight_pct' => $weight]);
                    $this->seedItems($category, $categoryItems, $students, $teacher, $salt);
                }
            }
        }
    }

    protected function seedItems(AssessmentCategory $category, array $items, $students, int $teacherId, string $salt = ''): void
    {
        foreach ($items as [$itemName, $max, $type]) {
            $item = Assessment::firstOrCreate(['category_id' => $category->id, 'name' => $itemName], ['type' => $type, 'max_score' => $max]);
            foreach ($students as $studentId) {
                $roll = crc32("{$studentId}-{$category->subject_id}-{$itemName}{$salt}");
                // Mostly 55-98%; about one mark in 25 is a fail (around 30%).
                $pct = $roll % 25 === 0 ? 30 : 55 + ($roll % 44);
                Grade::firstOrCreate(
                    ['assessment_id' => $item->id, 'student_id' => $studentId],
                    ['score' => round($max * $pct / 100, 1), 'entered_by' => $teacherId],
                );
            }
        }
    }

    /** Every weekday of the term, with some Absent, Tardy and Excused days. */
    protected function seedAttendance(Section $section, Term $term, $students, int $recorder): void
    {
        $rows = [];
        $now = now();
        $day = $term->start_date->copy();
        for ($i = 0; $day->lte($term->end_date); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }
            foreach ($students as $studentId) {
                $roll = ($studentId * 7 + $i) % 23;
                $rows[] = [
                    'student_id' => $studentId, 'section_id' => $section->id, 'term_id' => $term->id,
                    'attendance_date' => $day->toDateString(),
                    'status' => match ($roll) {
                        3 => 'Absent', 9 => 'Tardy', 15 => 'Excused', default => 'Present'
                    },
                    'recorded_by' => $recorder, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            $i++;
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            AttendanceRecord::insertOrIgnore($chunk);
        }
    }
}
