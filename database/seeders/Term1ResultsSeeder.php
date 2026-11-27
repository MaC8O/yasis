<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradebookTemplateCategory;
use App\Models\Guardian;
use App\Models\PersonalityRating;
use App\Models\ReportAcknowledgement;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\SubjectOffering;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Complete Term 1 marks and attendance for one section per grading scheme, so results show on a
 * fresh seed (and on staging): Grade 9-A (KG-9 scheme, fixed template; the demo student and
 * guardian's child are here), Grade 10-A (fixed template; its result stays blocked by Q6/Q19) and
 * Grade 12-A (Grades 11-12: the teacher's own categories). Term 1 is then frozen by DatabaseSeeder.
 * Marks are deterministic, mostly 55-98% with an occasional fail; attendance covers every weekday
 * of Term 1 with some Absent, Tardy and Excused days. The report cards get personality ratings
 * and every signature, so they print complete.
 */
class Term1ResultsSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->firstOrFail();
        $term = Term::where('academic_year_id', $year->id)->where('sequence', 1)->firstOrFail();
        $fallbackTeacher = StaffProfile::where('staff_id_number', 'USR-0005')->value('id');
        $template = GradebookTemplateCategory::ordered()->get();

        $items = [
            'quizzes' => [['Quiz 1', 10, 'quiz'], ['Quiz 2', 20, 'quiz']],
            'assignments' => [['Homework 1', 20, 'assignment'], ['Project', 30, 'assignment']],
            'participation' => [['Class participation', 10, 'classwork']],
            'examinations' => [['Mid-term exam', 50, 'test'], ['Final exam', 100, 'test']],
        ];
        $ownCategories = [
            ['Coursework', 40, [['Assignment 1', 20, 'assignment'], ['Lab report', 30, 'assignment']]],
            ['Examinations', 60, [['Mid-term exam', 50, 'test'], ['Final exam', 100, 'test']]],
        ];

        foreach (['Grade 9-A', 'Grade 10-A', 'Grade 12-A'] as $name) {
            $section = Section::where('academic_year_id', $year->id)->where('name', $name)->with('gradeLevel.gradingScheme')->first();
            if (! $section) {
                continue;
            }
            $students = Enrollment::where('section_id', $section->id)->where('status', 'Active')->orderBy('student_id')->pluck('student_id');
            $usesTemplate = (bool) $section->gradeLevel?->gradingScheme?->uses_gradebook_template;
            $recorder = $section->homeroom_teacher_id ?? $fallbackTeacher;

            $this->attendance($section, $term, $students, $recorder);
            $this->reportCard($section, $term, $students, $recorder);

            foreach (SubjectOffering::where('grade_level_id', $section->grade_level_id)->active()->get() as $offering) {
                $teacher = TeachingAssignment::where('section_id', $section->id)->where('subject_id', $offering->subject_id)->value('teacher_id') ?? $fallbackTeacher;
                $base = ['section_id' => $section->id, 'subject_id' => $offering->subject_id, 'term_id' => $term->id];

                if ($usesTemplate) {
                    // Term 1 is already locked here, so the categories are created directly, as the template defines them.
                    foreach ($template as $t) {
                        $category = AssessmentCategory::firstOrCreate($base + ['template_key' => $t->key], ['name' => $t->name, 'weight_pct' => $t->weight_pct]);
                        $this->items($category, $items[$t->key] ?? [], $students, $teacher);
                    }
                } else {
                    foreach ($ownCategories as [$categoryName, $weight, $categoryItems]) {
                        $category = AssessmentCategory::firstOrCreate($base + ['name' => $categoryName], ['weight_pct' => $weight]);
                        $this->items($category, $categoryItems, $students, $teacher);
                    }
                }
            }
        }
    }

    /**
     * The Term 1 report cards' personality ratings and signatures: the class teacher's
     * confirmation, the Principal's sign-off (Term 1 is released) and each student's first
     * guardian's acknowledgement.
     */
    private function reportCard(Section $section, Term $term, $students, int $teacherId): void
    {
        $traits = array_keys(PersonalityRating::TRAITS);
        foreach ($students as $studentId) {
            foreach ($traits as $i => $trait) {
                PersonalityRating::firstOrCreate(
                    ['student_id' => $studentId, 'term_id' => $term->id, 'trait' => $trait],
                    ['rating' => PersonalityRating::RATINGS[($studentId + $i) % 5 === 0 ? 1 : (($studentId * 3 + $i) % 7 === 0 ? 2 : 0)], 'rated_by' => $teacherId],
                );
            }
            ReportAcknowledgement::firstOrCreate(
                ['term_id' => $term->id, 'student_id' => $studentId, 'role' => ReportAcknowledgement::CLASS_TEACHER],
                ['section_id' => $section->id, 'user_id' => $teacherId, 'acknowledged_at' => $term->end_date->copy()->addDays(3)->setTime(15, 0)],
            );
            $guardianUser = Guardian::whereHas('students', fn ($q) => $q->where('students.id', $studentId))->orderBy('id')->value('user_id');
            if ($guardianUser) {
                ReportAcknowledgement::firstOrCreate(
                    ['term_id' => $term->id, 'student_id' => $studentId, 'role' => ReportAcknowledgement::GUARDIAN],
                    ['section_id' => $section->id, 'user_id' => $guardianUser, 'acknowledged_at' => $term->end_date->copy()->addDays(10)->setTime(19, 30)],
                );
            }
        }

        if ($term->results_released) {
            ReportAcknowledgement::firstOrCreate(
                ['term_id' => $term->id, 'student_id' => null, 'role' => ReportAcknowledgement::PRINCIPAL],
                ['user_id' => User::where('email', 'principal@yasis.edu')->value('id'), 'acknowledged_at' => $term->end_date->copy()->addDays(7)->setTime(9, 0)],
            );
        }
    }

    private function items(AssessmentCategory $category, array $items, $students, int $teacherId): void
    {
        foreach ($items as [$itemName, $max, $type]) {
            $item = Assessment::firstOrCreate(['category_id' => $category->id, 'name' => $itemName], ['type' => $type, 'max_score' => $max]);
            foreach ($students as $studentId) {
                $roll = crc32("{$studentId}-{$category->subject_id}-{$itemName}");
                // Mostly 55-98%; about one mark in 25 is a fail (around 30%).
                $pct = $roll % 25 === 0 ? 30 : 55 + ($roll % 44);
                Grade::firstOrCreate(
                    ['assessment_id' => $item->id, 'student_id' => $studentId],
                    ['score' => round($max * $pct / 100, 1), 'entered_by' => $teacherId],
                );
            }
        }
    }

    private function attendance(Section $section, Term $term, $students, int $recorder): void
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
