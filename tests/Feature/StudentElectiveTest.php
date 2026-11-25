<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentElective;
use App\Models\SubjectOffering;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Grades 11-12 elective choices (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3): the Registrar sets one
 * subject per slot per student per year; Q7 default, each slot chosen separately.
 */
class StudentElectiveTest extends TestCase
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

    private function offering(string $levelCode, string $subjectCode): SubjectOffering
    {
        return SubjectOffering::whereHas('gradeLevel', fn ($q) => $q->where('code', $levelCode))
            ->whereHas('subject', fn ($q) => $q->where('code', $subjectCode))->sole();
    }

    private function save(array $choices, ?Section $section = null)
    {
        return $this->actingAs($this->registrar->user)->put('/registrar/electives/'.($section ?? $this->grade11)->id, ['choices' => $choices]);
    }

    private function choice(Student $student, string $slot): ?string
    {
        return StudentElective::with('offering.subject')->where('student_id', $student->id)
            ->where('academic_year_id', $this->term->academic_year_id)->where('elective_slot', $slot)->first()?->offering->subject->code;
    }

    public function test_the_list_shows_sections_with_slots_and_how_many_choices_are_missing(): void
    {
        $this->section('Grade 9-A', '9');
        $this->section('Nursery', 'KG1')->update(['grade_level_id' => null]);

        $this->actingAs($this->registrar->user)->get('/registrar/electives')->assertOk()
            ->assertSee('Grade 11-A')->assertSee('slots A, B, C')->assertSee('9 choices missing')
            ->assertDontSee('Grade 9-A')->assertDontSee('Nursery')
            ->assertSee('Electives'); // navigation
    }

    public function test_the_section_page_offers_each_slots_subjects(): void
    {
        $page = $this->actingAs($this->registrar->user)->get("/registrar/electives/{$this->grade11->id}")->assertOk()
            ->assertSee('Slot A')->assertSee('Slot B')->assertSee('Slot C')
            ->assertSee('Aung Test')->assertSee('Saw Test');

        $html = $page->getContent();
        preg_match('/<select id="d-'.$this->students[0]->id.'-A"[^>]*>(.*?)<\/select>/s', $html, $m);
        $this->assertStringContainsString('Biology', $m[1]);
        $this->assertStringContainsString('World History', $m[1]);
        $this->assertStringNotContainsString('Chemistry', $m[1]);
    }

    public function test_the_registrar_sets_choices_and_each_is_audited(): void
    {
        [$a, $b] = $this->students;

        $this->save([
            $a->id => ['A' => $this->offering('11', 'BIO')->id, 'B' => $this->offering('11', 'CHEM')->id, 'C' => $this->offering('11', 'PHYS')->id],
            $b->id => ['A' => $this->offering('11', 'WHIST')->id, 'B' => '', 'C' => ''],
        ])->assertSessionHasNoErrors()->assertSessionHas('status', '4 elective choices saved.');

        $this->assertSame(['BIO', 'CHEM', 'PHYS'], [$this->choice($a, 'A'), $this->choice($a, 'B'), $this->choice($a, 'C')]);
        $this->assertSame(['WHIST', null], [$this->choice($b, 'A'), $this->choice($b, 'B')]);

        $logs = AuditLog::where('action', 'Set elective choice')->orderBy('id')->get();
        $this->assertCount(4, $logs);
        $this->assertTrue($logs->every(fn ($l) => $l->role === 'registrar'));
        $this->assertSame(['student_id' => $a->id, 'academic_year_id' => $this->term->academic_year_id, 'slot' => 'A', 'from' => null, 'to' => 'BIO'], $logs[0]->details);

        $this->actingAs($this->registrar->user)->get('/registrar/electives')->assertSee('5 choices missing');
    }

    public function test_a_choice_can_be_changed_but_not_cleared(): void
    {
        [$a] = $this->students;
        $this->save([$a->id => ['A' => $this->offering('11', 'BIO')->id]]);

        $this->save([$a->id => ['A' => $this->offering('11', 'WHIST')->id]])->assertSessionHas('status', '1 elective choice saved.');
        $this->assertSame('WHIST', $this->choice($a, 'A'));
        $this->assertSame(['from' => 'BIO', 'to' => 'WHIST'], collect(AuditLog::where('action', 'Changed elective choice')->sole()->details)->only(['from', 'to'])->all());

        $this->save([$a->id => ['A' => '']])->assertSessionHasErrors("choices.{$a->id}.A");
        $this->assertSame('WHIST', $this->choice($a, 'A'));

        $this->save([$a->id => ['A' => $this->offering('11', 'WHIST')->id]])->assertSessionHas('status', 'No choices were changed.');
    }

    public function test_slots_are_chosen_separately_so_a_mixed_combination_is_accepted(): void
    {
        [$a] = $this->students;

        $this->save([$a->id => ['A' => $this->offering('11', 'BIO')->id, 'B' => $this->offering('11', 'WGEO')->id, 'C' => $this->offering('11', 'ACCT')->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['BIO', 'WGEO', 'ACCT'], [$this->choice($a, 'A'), $this->choice($a, 'B'), $this->choice($a, 'C')]);
    }

    public function test_choices_outside_the_slot_level_or_section_are_refused(): void
    {
        [$a] = $this->students;
        $withdrawn = $this->enrol($this->student('Left Test'), $this->grade11, 'Withdrawn');
        $other = $this->enrol($this->student('Other Test'), $this->section('Grade 11-B', '11'));

        // Chemistry is slot B, not A.
        $this->save([$a->id => ['A' => $this->offering('11', 'CHEM')->id]])->assertSessionHasErrors("choices.{$a->id}.A");
        // Grade 12's Biology row is not Grade 11's.
        $this->save([$a->id => ['A' => $this->offering('12', 'BIO')->id]])->assertSessionHasErrors("choices.{$a->id}.A");
        // No slot D in Grade 11.
        $this->save([$a->id => ['D' => $this->offering('11', 'BIO')->id]])->assertSessionHasErrors('choices');
        // A compulsory row is not a choice.
        $this->save([$a->id => ['A' => $this->offering('11', 'MATH')->id]])->assertSessionHasErrors("choices.{$a->id}.A");
        // Withdrawn from this section, or in another section.
        $this->save([$withdrawn->id => ['A' => $this->offering('11', 'BIO')->id]])->assertSessionHasErrors('choices');
        $this->save([$other->id => ['A' => $this->offering('11', 'BIO')->id]])->assertSessionHasErrors('choices');

        $this->assertSame(0, StudentElective::count());
        $this->assertSame(0, AuditLog::where('entity_type', 'StudentElective')->count());
    }

    public function test_a_retired_subject_is_not_offered_or_accepted(): void
    {
        [$a] = $this->students;
        $history = $this->offering('11', 'WHIST');
        $history->update(['archived_at' => now()]);

        $this->actingAs($this->registrar->user)->get("/registrar/electives/{$this->grade11->id}")->assertOk()->assertDontSee('World History');
        $this->save([$a->id => ['A' => $history->id]])->assertSessionHasErrors("choices.{$a->id}.A");
    }

    public function test_a_section_without_slots_has_no_electives_page(): void
    {
        $grade9 = $this->section('Grade 9-A', '9');
        $student = $this->enrol($this->student('Nine Test'), $grade9);

        $this->actingAs($this->registrar->user)->get("/registrar/electives/{$grade9->id}")->assertNotFound();
        $this->save([$student->id => ['A' => $this->offering('11', 'BIO')->id]], $grade9)->assertSessionHasErrors('choices');
        $this->assertSame(0, StudentElective::count());
    }

    public function test_only_the_registrar_sets_electives(): void
    {
        [$a] = $this->students;
        foreach ([['teacher', 'Teacher'], ['admin', 'Admin'], ['principal', 'Principal'], ['vp_academic', 'VP_Academic']] as [$role, $type]) {
            $user = $this->makeStaff($role, $type, "{$role}@test.local")->user;
            $this->actingAs($user)->get('/registrar/electives')->assertForbidden();
            $this->actingAs($user)->put("/registrar/electives/{$this->grade11->id}", ['choices' => [$a->id => ['A' => $this->offering('11', 'BIO')->id]]])->assertForbidden();
        }

        $this->assertSame(0, StudentElective::count());
    }
}
