<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentTrack;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Grade 10 and Grades 11-12 tracks (the school's answers to Q6 and Q7): the Registrar sets one track,
 * science or arts, per student per year; it replaces the per-slot elective choice.
 */
class StudentTrackTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $registrar;

    private Department $department;

    private Term $term;

    private Section $grade11;

    /** @var list<Student> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->department = $this->seedDepartment();
        $this->term = $this->seedAcademicCalendar();
        $this->registrar = $this->makeStaff('registrar', 'Registrar');
        $this->grade11 = $this->section('Grade 11-A', '11');
        foreach (['Aung Test', 'Ma Test', 'Saw Test'] as $name) {
            $this->students[] = $this->enrol($this->student($name), $this->grade11);
        }
    }

    private function section(string $name, string $levelCode): Section
    {
        return Section::create(['academic_year_id' => $this->term->academic_year_id, 'department_id' => $this->department->id,
            'grade_level_id' => GradeLevel::where('code', $levelCode)->value('id'), 'name' => $name, 'capacity' => 30]);
    }

    private function student(string $name): Student
    {
        return Student::create(['student_id_number' => 'S-'.fake()->unique()->numberBetween(1000, 99999), 'name' => $name,
            'admission_date' => now()->subYear(), 'department_id' => $this->department->id, 'enrollment_status' => 'Enrolled']);
    }

    private function enrol(Student $student, Section $section, string $status = 'Active'): Student
    {
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => $status]);

        return $student;
    }

    private function save(array $tracks, ?Section $section = null)
    {
        return $this->actingAs($this->registrar->user)->put('/registrar/tracks/'.($section ?? $this->grade11)->id, ['tracks' => $tracks]);
    }

    private function track(Student $student): ?string
    {
        return StudentTrack::where('student_id', $student->id)->where('academic_year_id', $this->term->academic_year_id)->value('track');
    }

    public function test_the_list_shows_grade_10_to_12_sections_and_how_many_have_no_track(): void
    {
        $this->section('Grade 10-A', '10');
        $this->section('Grade 9-A', '9');
        StudentTrack::create(['student_id' => $this->students[0]->id, 'academic_year_id' => $this->term->academic_year_id, 'track' => 'science']);

        $this->actingAs($this->registrar->user)->get('/registrar/tracks')->assertOk()
            ->assertSee('Grade 11-A')->assertSee('2 without a track')->assertSee('Science 1')
            ->assertSee('Grade 10-A')->assertDontSee('Grade 9-A');
        $this->actingAs($this->registrar->user)->get('/registrar/electives')->assertRedirect('/registrar/tracks');
    }

    public function test_the_section_page_lists_each_tracks_subjects(): void
    {
        $this->actingAs($this->registrar->user)->get("/registrar/tracks/{$this->grade11->id}")->assertOk()
            ->assertSeeInOrder(['Arts', 'World History, World Geography, Accounting', 'Science', 'Biology, Chemistry, Physics'])
            ->assertSee('No track yet');

        $grade10 = $this->section('Grade 10-A', '10');
        $this->actingAs($this->registrar->user)->get("/registrar/tracks/{$grade10->id}")->assertOk()
            ->assertSee('World History, World Geography, Music');
    }

    public function test_the_registrar_sets_tracks_and_each_is_audited(): void
    {
        [$a, $b, $c] = $this->students;

        $this->save([$a->id => 'science', $b->id => 'arts', $c->id => ''])->assertSessionHas('status', '2 tracks saved.');

        $this->assertSame(['science', 'arts', null], [$this->track($a), $this->track($b), $this->track($c)]);
        $this->assertSame($this->registrar->user->id, StudentTrack::where('student_id', $a->id)->value('set_by'));
        $this->assertSame(2, AuditLog::where('action', 'Set student track')->count());

        // Changing one: audited with before and after; saving the same again changes nothing.
        $this->save([$a->id => 'arts', $b->id => 'arts'])->assertSessionHas('status', '1 track saved.');
        $log = AuditLog::where('action', 'Changed student track')->sole();
        $this->assertSame(['science', 'arts'], [$log->details['from'], $log->details['to']]);
        $this->save([$a->id => 'arts'])->assertSessionHas('status', 'No tracks were changed.');
    }

    public function test_a_track_can_be_changed_but_not_cleared(): void
    {
        [$a] = $this->students;
        $this->save([$a->id => 'science']);

        $this->save([$a->id => ''])->assertSessionHasErrors("tracks.{$a->id}");
        $this->assertSame('science', $this->track($a));
    }

    public function test_a_track_the_grade_does_not_offer_or_a_student_outside_the_section_is_refused(): void
    {
        [$a] = $this->students;
        $this->save([$a->id => 'music'])->assertSessionHasErrors("tracks.{$a->id}");

        $other = $this->enrol($this->student('Other Class'), $this->section('Grade 12-A', '12'));
        $this->save([$other->id => 'science'])->assertSessionHasErrors('tracks');

        $withdrawn = $this->enrol($this->student('Left Test'), $this->grade11, 'Withdrawn');
        $this->save([$withdrawn->id => 'science'])->assertSessionHasErrors('tracks');
        $this->assertSame(0, StudentTrack::count());
    }

    public function test_a_section_without_tracks_has_no_tracks_page(): void
    {
        $grade9 = $this->section('Grade 9-A', '9');
        $student = $this->enrol($this->student('Nine Test'), $grade9);

        $this->actingAs($this->registrar->user)->get("/registrar/tracks/{$grade9->id}")->assertNotFound();
        $this->save([$student->id => 'science'], $grade9)->assertSessionHasErrors("tracks.{$student->id}");
        $this->assertSame(0, StudentTrack::count());
    }

    public function test_only_the_registrar_sets_tracks(): void
    {
        [$a] = $this->students;
        foreach ([['teacher', 'Teacher'], ['admin', 'Admin'], ['principal', 'Principal'], ['vp_academic', 'VP_Academic']] as [$role, $type]) {
            $user = $this->makeStaff($role, $type, "{$role}@test.local")->user;
            $this->actingAs($user)->get('/registrar/tracks')->assertForbidden();
            $this->actingAs($user)->put("/registrar/tracks/{$this->grade11->id}", ['tracks' => [$a->id => 'science']])->assertForbidden();
        }

        $this->assertSame(0, StudentTrack::count());
    }
}
