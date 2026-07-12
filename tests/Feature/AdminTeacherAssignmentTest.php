<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §6.3 Admin Teacher Class Assignment: technical setup/override of homeroom +
 * subject-teacher assignments. UQ(section, subject) — a duplicate assignment
 * is refused, never silently overwritten (and never a raw 500).
 */
class AdminTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function setUpStructure(): array
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $admin = $this->makeStaff('admin', 'Admin');
        $teacherA = $this->makeStaff('teacher', 'Teacher', 'teacher.a@test.local');
        $teacherB = $this->makeStaff('teacher', 'Teacher', 'teacher.b@test.local');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $subject = Subject::create(['code' => 'MAT9', 'name' => 'Mathematics', 'department_id' => $department->id]);

        return [$admin, $teacherA, $teacherB, $section, $subject];
    }

    public function test_admin_can_assign_a_subject_teacher(): void
    {
        [$admin, $teacherA, , $section, $subject] = $this->setUpStructure();

        $this->actingAs($admin->user)->post('/admin/teacher-assignments', [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherA->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teaching_assignments', [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherA->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Assigned teacher to section/subject']);
    }

    public function test_assigning_the_same_subject_twice_to_one_section_is_refused(): void
    {
        [$admin, $teacherA, $teacherB, $section, $subject] = $this->setUpStructure();
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherA->id]);

        $this->actingAs($admin->user)->post('/admin/teacher-assignments', [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherB->id,
        ])->assertSessionHasErrors('subject_id');

        $this->assertSame(1, TeachingAssignment::count());
        $this->assertSame($teacherA->id, TeachingAssignment::first()->teacher_id);
    }

    public function test_the_registrar_and_vp_assignment_paths_also_refuse_duplicates(): void
    {
        [, $teacherA, $teacherB, $section, $subject] = $this->setUpStructure();
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherA->id]);

        $payload = ['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherB->id];

        $this->actingAs($registrar->user)->post('/registrar/teaching-assignments', $payload)
            ->assertSessionHasErrors('subject_id');
        $this->actingAs($vp->user)->post('/vp_academic/assignments', $payload)
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(1, TeachingAssignment::count());
    }

    public function test_admin_can_reassign_a_teacher_on_an_existing_row(): void
    {
        [$admin, $teacherA, $teacherB, $section, $subject] = $this->setUpStructure();
        $assignment = TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherA->id]);

        $this->actingAs($admin->user)->put("/admin/teacher-assignments/{$assignment->id}", [
            'teacher_id' => $teacherB->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($teacherB->id, $assignment->fresh()->teacher_id);
        $this->assertSame(1, TeachingAssignment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Reassigned teacher for section/subject']);
    }

    public function test_homeroom_override_requires_a_teacher(): void
    {
        [$admin, $teacherA, , $section] = $this->setUpStructure();
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');

        $this->actingAs($admin->user)->put("/admin/teacher-assignments/sections/{$section->id}/homeroom", [
            'homeroom_teacher_id' => $hr->id,
        ])->assertSessionHasErrors('homeroom_teacher_id');

        $this->actingAs($admin->user)->put("/admin/teacher-assignments/sections/{$section->id}/homeroom", [
            'homeroom_teacher_id' => $teacherA->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($teacherA->id, $section->fresh()->homeroom_teacher_id);
    }

    public function test_the_page_renders_sections_with_assignments(): void
    {
        [$admin, $teacherA, , $section, $subject] = $this->setUpStructure();
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherA->id]);

        $this->actingAs($admin->user)->get('/admin/teacher-assignments')
            ->assertOk()
            ->assertSee('Teacher Class Assignment')
            ->assertSee('Grade 9-A')
            ->assertSee('Mathematics');
    }

    public function test_non_admins_cannot_use_the_admin_assignment_screen(): void
    {
        [, $teacherA, , $section, $subject] = $this->setUpStructure();

        $this->actingAs($teacherA->user)->post('/admin/teacher-assignments', [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherA->id,
        ])->assertForbidden();
    }
}
