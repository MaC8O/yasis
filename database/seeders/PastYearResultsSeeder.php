<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\PersonalityRating;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\StudentTrack;
use App\Models\Term;
use App\Models\User;
use App\Services\ResultFreezeService;
use Database\Seeders\Concerns\SeedsTermResults;
use Illuminate\Database\Seeder;

/**
 * Last school year, complete and frozen, so a Statement of Marks (§7.2) can be generated for each
 * layout on a fresh seed: Grade 8-A (KG1-9 layout) holds the students now in Grade 9-A, and
 * Grade 11-A (Grades 11-12 layout) those now in Grade 12-A, with their option choices. Their
 * enrollments are Completed, as an applied promotion leaves them. Every term gets a full gradebook
 * and attendance, then the Principal locks all four through ResultFreezeService, which freezes the
 * year. No teaching assignments are made for these sections, so this year's timetables don't change.
 */
class PastYearResultsSeeder extends Seeder
{
    use SeedsTermResults;

    /** this year's section => [last year's section, its grade level code] */
    private const SECTIONS = [
        'Grade 9-A' => ['Grade 8-A', '8'],
        'Grade 12-A' => ['Grade 11-A', '11'],
    ];

    public function run(): void
    {
        $current = AcademicYear::where('is_active', true)->firstOrFail();
        [$from, $to] = array_map('intval', explode('-', $current->year_label));
        $year = AcademicYear::firstOrCreate(['year_label' => ($from - 1).'-'.($to - 1)], ['is_active' => false]);

        foreach (Term::where('academic_year_id', $current->id)->orderBy('sequence')->get() as $t) {
            Term::firstOrCreate(['academic_year_id' => $year->id, 'sequence' => $t->sequence], [
                'name' => $t->name, 'start_date' => $t->start_date->copy()->subYear(), 'end_date' => $t->end_date->copy()->subYear(),
                'results_released' => true,
            ]);
        }
        $terms = Term::where('academic_year_id', $year->id)->orderBy('sequence')->get();
        $fallbackTeacher = StaffProfile::where('staff_id_number', 'USR-0005')->value('id');

        foreach (self::SECTIONS as $currentName => [$pastName, $levelCode]) {
            $now = Section::where('academic_year_id', $current->id)->where('name', $currentName)->first();
            $level = GradeLevel::where('code', $levelCode)->first();
            if (! $now || ! $level) {
                continue;
            }
            $section = Section::firstOrCreate(['academic_year_id' => $year->id, 'name' => $pastName], [
                // No homeroom teacher: the teacher pages list a teacher's homeroom sections of every
                // year, so last year's class would show beside this year's (noted in PR #49).
                'department_id' => $now->department_id, 'grade_level_id' => $level->id, 'capacity' => $now->capacity,
            ]);
            $section->load('gradeLevel.gradingScheme');

            $students = Enrollment::where('section_id', $now->id)->where('status', 'Active')->orderBy('student_id')->pluck('student_id');
            foreach ($students as $studentId) {
                Enrollment::firstOrCreate(['student_id' => $studentId, 'section_id' => $section->id], ['status' => 'Completed']);
            }
            $this->copyTracks($students, $current, $year);

            foreach ($terms as $term) {
                $this->seedAttendance($section, $term, $students, $now->homeroom_teacher_id ?? $fallbackTeacher);
                $this->seedGradebook($section, $term, $students, "-{$year->year_label}-{$term->sequence}");
            }
            $this->rateTraits($students, $terms->last(), $now->homeroom_teacher_id ?? $fallbackTeacher);
        }

        // Lock every term as the Principal: each term is frozen, and the year with the last one.
        $principal = User::where('email', 'principal@yasis.edu')->firstOrFail();
        foreach ($terms as $term) {
            if (! $term->fresh()->is_locked) {
                app(ResultFreezeService::class)->lock($term, $principal);
            }
        }
    }

    /** Last year's tracks: the same as this year's (Grade 11 then 12). */
    private function copyTracks($students, AcademicYear $current, AcademicYear $year): void
    {
        foreach (StudentTrack::where('academic_year_id', $current->id)->whereIn('student_id', $students)->get() as $track) {
            StudentTrack::firstOrCreate(['student_id' => $track->student_id, 'academic_year_id' => $year->id],
                ['track' => $track->track, 'set_by' => $track->set_by]);
        }
    }

    /** Last year's personality ratings: once a year, on the last term (Q14), so the final card prints them. */
    private function rateTraits($students, Term $last, int $teacherId): void
    {
        $userId = $teacherId; // a staff profile's id is its user's id
        foreach ($students as $studentId) {
            foreach (array_keys(PersonalityRating::TRAITS) as $i => $trait) {
                PersonalityRating::firstOrCreate(['student_id' => $studentId, 'term_id' => $last->id, 'trait' => $trait],
                    ['rating' => PersonalityRating::RATINGS[($studentId + $i) % 4 === 0 ? 1 : 0], 'rated_by' => $userId]);
            }
        }
    }
}
