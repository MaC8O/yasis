<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\PromotionBatch;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Registrar class management: section create/edit/archive/delete (guarded), homeroom
 * warnings, and subject-teacher assignments (add, change, guarded remove, bulk save).
 */
class RegistrarClassManagementTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $registrar;
    private StaffProfile $teacherA;
    private StaffProfile $teacherB;
    private Department $department;
    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->department = $this->seedDepartment();
        $this->term = $this->seedAcademicCalendar();
        $this->registrar = $this->makeStaff('registrar', 'Registrar');
        $this->teacherA = $this->makeStaff('teacher', 'Teacher', 'teacher.a@test.local');
        $this->teacherB = $this->makeStaff('teacher', 'Teacher', 'teacher.b@test.local');
    }

    private function section(string $name = 'Grade 9-A', array $extra = []): Section
    {
        return Section::create(array_merge([
            'academic_year_id' => $this->term->academic_year_id,
            'department_id' => $this->department->id,
            'grade_level_id' => $this->level('9'),
            'name' => $name,
            'capacity' => 35,
        ], $extra));
    }

    private function level(string $code): int
    {
        return GradeLevel::where('code', $code)->value('id');
    }

    private function subject(string $code, string $name): Subject
    {
        return Subject::create(['code' => $code, 'name' => $name, 'department_id' => $this->department->id]);
    }

    private function student(): Student
    {
        return Student::create([
            'student_id_number' => 'S-'.fake()->unique()->numberBetween(1000, 99999),
            'name' => 'Aye Min',
            'date_of_birth' => now()->subYears(14),
            'gender' => 'M',
            'department_id' => $this->department->id,
            'enrollment_status' => 'Enrolled',
            'admission_date' => now()->subYear(),
        ]);
    }

    private function gradeFor(Section $section, Subject $subject): Grade
    {
        $category = AssessmentCategory::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $this->term->id, 'name' => 'Quiz', 'weight_pct' => 100]);
        $assessment = Assessment::create(['category_id' => $category->id, 'name' => 'Quiz 1', 'max_score' => 100]);

        return Grade::create(['assessment_id' => $assessment->id, 'student_id' => $this->student()->id, 'score' => 80, 'entered_by' => $this->teacherA->id]);
    }

    private function asRegistrar()
    {
        return $this->actingAs($this->registrar->user);
    }

    // ---------------------------------------------------------------- sections

    public function test_registrar_creates_a_section_in_the_active_year_by_default_and_it_is_audited_with_role(): void
    {
        $this->asRegistrar()->post('/registrar/sections', [
            'name' => 'Grade 10-B', 'department_id' => $this->department->id, 'grade_level_id' => $this->level('10'), 'homeroom_teacher_id' => $this->teacherA->id, 'capacity' => 30,
        ])->assertSessionHasNoErrors();

        $section = Section::where('name', 'Grade 10-B')->firstOrFail();
        $this->assertSame($this->term->academic_year_id, $section->academic_year_id);
        $this->assertSame($this->teacherA->id, $section->homeroom_teacher_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Created section', 'entity_id' => $section->id, 'role' => 'registrar']);
    }

    public function test_registrar_can_create_a_section_in_another_academic_year(): void
    {
        $next = AcademicYear::create(['year_label' => '2099-2100', 'is_active' => false]);

        $this->asRegistrar()->post('/registrar/sections', [
            'name' => 'Grade 9-A', 'department_id' => $this->department->id, 'grade_level_id' => $this->level('9'), 'academic_year_id' => $next->id, 'capacity' => 30,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sections', ['name' => 'Grade 9-A', 'academic_year_id' => $next->id]);
    }

    public function test_homeroom_teacher_already_used_elsewhere_warns_but_saves(): void
    {
        $this->section('Grade 9-A', ['homeroom_teacher_id' => $this->teacherA->id]);

        $this->asRegistrar()->post('/registrar/sections', [
            'name' => 'Grade 9-B', 'department_id' => $this->department->id, 'grade_level_id' => $this->level('9'), 'homeroom_teacher_id' => $this->teacherA->id, 'capacity' => 30,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Grade 9-A'));

        $this->assertSame($this->teacherA->id, Section::where('name', 'Grade 9-B')->value('homeroom_teacher_id'));
    }

    public function test_registrar_can_rename_change_department_and_homeroom_and_the_change_is_audited(): void
    {
        $section = $this->section();
        $middle = $this->seedDepartment('Middle School');

        $this->asRegistrar()->put("/registrar/sections/{$section->id}", [
            'name' => 'Grade 8-C', 'department_id' => $middle->id, 'homeroom_teacher_id' => $this->teacherB->id, 'capacity' => 30,
        ])->assertSessionHasNoErrors();

        $section->refresh();
        $this->assertSame('Grade 8-C', $section->name);
        $this->assertSame($middle->id, $section->department_id);
        $this->assertSame($this->teacherB->id, $section->homeroom_teacher_id);
        $log = \App\Models\AuditLog::where('action', 'Updated section')->where('entity_id', $section->id)->firstOrFail();
        $this->assertSame('Grade 9-A', $log->details['changes']['name']['from']);
    }

    public function test_homeroom_can_be_cleared_and_must_be_a_teacher(): void
    {
        $section = $this->section('Grade 9-A', ['homeroom_teacher_id' => $this->teacherA->id]);

        $this->asRegistrar()->put("/registrar/sections/{$section->id}", ['homeroom_teacher_id' => $this->registrar->id])
            ->assertSessionHasErrors('homeroom_teacher_id');
        $this->assertSame($this->teacherA->id, $section->fresh()->homeroom_teacher_id);

        $this->asRegistrar()->put("/registrar/sections/{$section->id}", ['homeroom_teacher_id' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($section->fresh()->homeroom_teacher_id);
    }

    public function test_an_empty_section_can_be_deleted_and_its_assignments_go_with_it(): void
    {
        $section = $this->section();
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $this->subject('MATH9', 'Mathematics')->id, 'teacher_id' => $this->teacherA->id]);

        $this->asRegistrar()->delete("/registrar/sections/{$section->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect('/registrar/sections');

        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
        $this->assertSame(0, TeachingAssignment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Deleted section', 'entity_id' => $section->id, 'role' => 'registrar']);
    }

    public function test_delete_is_blocked_when_students_are_enrolled_and_archive_is_offered(): void
    {
        $section = $this->section();
        Enrollment::create(['student_id' => $this->student()->id, 'section_id' => $section->id, 'status' => 'Active']);

        $this->asRegistrar()->delete("/registrar/sections/{$section->id}")
            ->assertSessionHasErrors(['section' => 'Grade 9-A can\'t be deleted because it has 1 student enrollment. Archive it instead to keep its history.'])
            ->assertSessionHas('archiveOffer', $section->id);

        $this->assertDatabaseHas('sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Deleted section']);
    }

    public function test_delete_is_blocked_by_attendance_records_alone(): void
    {
        $section = $this->section();
        AttendanceRecord::create(['student_id' => $this->student()->id, 'section_id' => $section->id, 'term_id' => $this->term->id,
            'attendance_date' => today()->toDateString(), 'status' => 'Present', 'recorded_by' => $this->teacherA->id]);

        $this->asRegistrar()->delete("/registrar/sections/{$section->id}")->assertSessionHasErrors('section');
        $this->assertDatabaseHas('sections', ['id' => $section->id]);
        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_delete_is_blocked_by_grades_alone(): void
    {
        $section = $this->section();
        $this->gradeFor($section, $this->subject('MATH9', 'Mathematics'));

        $this->asRegistrar()->delete("/registrar/sections/{$section->id}")
            ->assertSessionHasErrors(['section' => 'Grade 9-A can\'t be deleted because it has 1 grade. Archive it instead to keep its history.']);
        $this->assertSame(1, Grade::count());
    }

    public function test_delete_is_blocked_by_a_promotion_batch_instead_of_failing(): void
    {
        $section = $this->section();
        PromotionBatch::create(['from_section_id' => $section->id, 'prepared_by' => $this->registrar->id, 'status' => 'Pending']);

        $this->asRegistrar()->delete("/registrar/sections/{$section->id}")->assertSessionHasErrors('section');
        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_archive_hides_the_section_keeps_its_records_and_restore_brings_it_back(): void
    {
        $section = $this->section();
        Enrollment::create(['student_id' => $this->student()->id, 'section_id' => $section->id, 'status' => 'Active']);

        $this->asRegistrar()->post("/registrar/sections/{$section->id}/archive")->assertSessionHasNoErrors();
        $this->assertNotNull($section->fresh()->archived_at);
        $this->assertSame(1, Enrollment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Archived section', 'entity_id' => $section->id]);

        $this->asRegistrar()->get('/registrar/sections')->assertOk()->assertDontSee('Grade 9-A</a>', false);
        $this->asRegistrar()->get('/registrar/sections?archived=1')->assertOk()->assertSee('Archived');

        // Archived sections can't receive new students or teachers.
        $this->asRegistrar()->post("/registrar/sections/{$section->id}/enroll", ['student_ids' => [$this->student()->id]])
            ->assertSessionHasErrors('student_ids');
        $this->asRegistrar()->post('/registrar/teaching-assignments', [
            'section_id' => $section->id, 'subject_id' => $this->subject('MATH9', 'Mathematics')->id, 'teacher_id' => $this->teacherA->id,
        ])->assertSessionHasErrors('section_id');

        $this->asRegistrar()->post("/registrar/sections/{$section->id}/restore")->assertSessionHasNoErrors();
        $this->assertNull($section->fresh()->archived_at);
    }

    public function test_sections_page_groups_by_department_searches_by_teacher_and_shows_coverage(): void
    {
        $section = $this->section('Grade 9-A', ['homeroom_teacher_id' => $this->teacherA->id]);
        $this->section('Grade 9-B');
        $math9 = $this->subject('MATH9', 'Mathematics 9');
        $this->subject('ENG9', 'English 9');
        $this->subject('ENG10', 'English 10'); // other grade — not expected in Grade 9
        $this->subject('HS-BIB', 'Bible');
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $math9->id, 'teacher_id' => $this->teacherB->id]);

        $this->asRegistrar()->get('/registrar/sections')
            ->assertOk()->assertSee('High School')->assertSee('1 / 3');

        $this->asRegistrar()->get('/registrar/sections?search='.urlencode($this->teacherB->user->name))
            ->assertOk()->assertSee('Grade 9-A')->assertDontSee('Grade 9-B</a>', false);
    }

    public function test_section_detail_page_shows_both_tabs_and_expected_subjects(): void
    {
        $section = $this->section();
        $this->subject('MATH9', 'Mathematics 9');
        $this->subject('MATH10', 'Mathematics 10');
        Enrollment::create(['student_id' => $this->student()->id, 'section_id' => $section->id, 'status' => 'Active']);

        $this->asRegistrar()->get("/registrar/sections/{$section->id}")
            ->assertOk()
            ->assertSee('Teaching assignments')->assertSee('Students (1)')
            ->assertSee('Mathematics 9')->assertDontSee('Mathematics 10')
            ->assertSee('Aye Min');
    }

    // ---------------------------------------------------------------- teaching assignments

    public function test_assignment_requires_a_teacher_and_duplicates_give_a_readable_error(): void
    {
        $section = $this->section();
        $math = $this->subject('MATH9', 'Mathematics');

        $this->asRegistrar()->post('/registrar/teaching-assignments', ['section_id' => $section->id, 'subject_id' => $math->id, 'teacher_id' => $this->registrar->id])
            ->assertSessionHasErrors(['teacher_id' => 'Only staff with the Teacher role can be assigned to teach a subject.']);

        $this->asRegistrar()->post('/registrar/teaching-assignments', ['section_id' => $section->id, 'subject_id' => $math->id, 'teacher_id' => $this->teacherA->id])
            ->assertSessionHasNoErrors();
        $this->asRegistrar()->post('/registrar/teaching-assignments', ['section_id' => $section->id, 'subject_id' => $math->id, 'teacher_id' => $this->teacherB->id])
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, TeachingAssignment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Assigned teacher to section/subject', 'role' => 'registrar']);
    }

    public function test_registrar_can_change_the_teacher_on_an_assignment(): void
    {
        $assignment = TeachingAssignment::create(['section_id' => $this->section()->id, 'subject_id' => $this->subject('MATH9', 'Mathematics')->id, 'teacher_id' => $this->teacherA->id]);

        $this->asRegistrar()->put("/registrar/teaching-assignments/{$assignment->id}", ['teacher_id' => $this->teacherB->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->teacherB->id, $assignment->fresh()->teacher_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Reassigned teacher for section/subject', 'entity_id' => $assignment->id, 'role' => 'registrar']);
    }

    public function test_removing_an_assignment_without_grades_works(): void
    {
        $assignment = TeachingAssignment::create(['section_id' => $this->section()->id, 'subject_id' => $this->subject('MATH9', 'Mathematics')->id, 'teacher_id' => $this->teacherA->id]);

        $this->asRegistrar()->delete("/registrar/teaching-assignments/{$assignment->id}")->assertSessionHasNoErrors();

        $this->assertSame(0, TeachingAssignment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Removed teaching assignment', 'entity_id' => $assignment->id]);
    }

    public function test_removing_an_assignment_with_grades_is_blocked_for_registrar_vp_and_admin(): void
    {
        $section = $this->section();
        $math = $this->subject('MATH9', 'Mathematics');
        $assignment = TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $math->id, 'teacher_id' => $this->teacherA->id]);
        $this->gradeFor($section, $math);
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $this->asRegistrar()->delete("/registrar/teaching-assignments/{$assignment->id}")
            ->assertSessionHasErrors(['assignment' => "Can't remove Mathematics from Grade 9-A: 1 grade is already recorded for it. Change the teacher instead."]);
        $this->actingAs($vp->user)->delete("/vp_academic/assignments/{$assignment->id}")->assertSessionHasErrors('assignment');
        $this->actingAs($admin->user)->post('/admin/teacher-assignments/set', ['section_id' => $section->id, 'subject_id' => $math->id, 'teacher_id' => ''])
            ->assertSessionHasErrors('assignment');

        $this->assertSame(1, TeachingAssignment::count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Removed teaching assignment']);
    }

    public function test_bulk_save_assigns_changes_and_clears_in_one_request(): void
    {
        $section = $this->section();
        [$math, $eng, $bib] = [$this->subject('MATH9', 'Mathematics'), $this->subject('ENG9', 'English'), $this->subject('HS-BIB', 'Bible')];
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $eng->id, 'teacher_id' => $this->teacherA->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $bib->id, 'teacher_id' => $this->teacherA->id]);

        $this->asRegistrar()->put("/registrar/sections/{$section->id}/assignments", ['teachers' => [
            $math->id => $this->teacherA->id,   // new
            $eng->id => $this->teacherB->id,    // change
            $bib->id => '',                     // clear
        ]])->assertSessionHasNoErrors()->assertSessionHas('status', 'Saved: 1 assigned, 1 changed, 1 removed.');

        $this->assertSame($this->teacherA->id, TeachingAssignment::where('subject_id', $math->id)->value('teacher_id'));
        $this->assertSame($this->teacherB->id, TeachingAssignment::where('subject_id', $eng->id)->value('teacher_id'));
        $this->assertNull(TeachingAssignment::where('subject_id', $bib->id)->first());
        $this->assertSame(3, \App\Models\AuditLog::where('role', 'registrar')->whereIn('action', [
            'Assigned teacher to section/subject', 'Reassigned teacher for section/subject', 'Removed teaching assignment',
        ])->count());
    }

    public function test_bulk_save_is_all_or_nothing_when_a_graded_subject_would_be_cleared(): void
    {
        $section = $this->section();
        [$math, $eng] = [$this->subject('MATH9', 'Mathematics'), $this->subject('ENG9', 'English')];
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $math->id, 'teacher_id' => $this->teacherA->id]);
        $this->gradeFor($section, $math);

        $this->asRegistrar()->put("/registrar/sections/{$section->id}/assignments", ['teachers' => [
            $eng->id => $this->teacherB->id,
            $math->id => '',
        ]])->assertSessionHasErrors('assignment');

        $this->assertNull(TeachingAssignment::where('subject_id', $eng->id)->first(), 'the English assignment must be rolled back');
        $this->assertSame(1, TeachingAssignment::count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Assigned teacher to section/subject']);
    }

    public function test_bulk_save_rejects_non_teachers(): void
    {
        $section = $this->section();
        $math = $this->subject('MATH9', 'Mathematics');

        $this->asRegistrar()->put("/registrar/sections/{$section->id}/assignments", ['teachers' => [$math->id => $this->registrar->id]])
            ->assertSessionHasErrors("teachers.{$math->id}");
        $this->assertSame(0, TeachingAssignment::count());
    }

    public function test_other_roles_cannot_use_the_registrar_class_management_routes(): void
    {
        $section = $this->section();

        foreach ([
            ['get', "/registrar/sections/{$section->id}"],
            ['delete', "/registrar/sections/{$section->id}"],
            ['post', "/registrar/sections/{$section->id}/archive"],
            ['put', "/registrar/sections/{$section->id}/assignments"],
        ] as [$method, $url]) {
            $this->actingAs($this->teacherA->user)->{$method}($url)->assertForbidden();
        }

        $this->assertDatabaseHas('sections', ['id' => $section->id, 'archived_at' => null]);
    }
}
