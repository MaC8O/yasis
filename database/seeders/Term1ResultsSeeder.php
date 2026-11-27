<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\PersonalityRating;
use App\Models\ReportAcknowledgement;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\Concerns\SeedsTermResults;
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
    use SeedsTermResults;

    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->firstOrFail();
        $term = Term::where('academic_year_id', $year->id)->where('sequence', 1)->firstOrFail();
        $fallbackTeacher = StaffProfile::where('staff_id_number', 'USR-0005')->value('id');

        foreach (['Grade 9-A', 'Grade 10-A', 'Grade 12-A'] as $name) {
            $section = Section::where('academic_year_id', $year->id)->where('name', $name)->with('gradeLevel.gradingScheme')->first();
            if (! $section) {
                continue;
            }
            $students = Enrollment::where('section_id', $section->id)->where('status', 'Active')->orderBy('student_id')->pluck('student_id');
            $recorder = $section->homeroom_teacher_id ?? $fallbackTeacher;

            $this->seedAttendance($section, $term, $students, $recorder);
            $this->reportCard($section, $term, $students, $recorder);
            $this->seedGradebook($section, $term, $students);
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
}
