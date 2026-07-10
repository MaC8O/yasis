<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §3.6 Module 3: the VP Academic owns the subject catalogue and
 * subject-teaching assignments.
 */
class VpSubjectCatalogTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_vp_can_add_a_subject_and_assign_a_teacher(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);

        $this->actingAs($vp->user)->post('/vp_academic/subjects', [
            'code' => 'SCI9', 'name' => 'Science', 'department_id' => $department->id,
        ])->assertSessionHasNoErrors();

        $subject = Subject::where('code', 'SCI9')->firstOrFail();

        $this->actingAs($vp->user)->post('/vp_academic/assignments', [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teaching_assignments', [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);
    }

    public function test_a_subject_with_assignments_cannot_be_removed(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);

        $subject = Subject::create(['code' => 'ENG9', 'name' => 'English', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $this->actingAs($vp->user)->delete("/vp_academic/subjects/{$subject->id}")
            ->assertSessionHasErrors('subject');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_subjects_page_renders_for_the_vp(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $this->seedAcademicCalendar();
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        Subject::create(['code' => 'GEO9', 'name' => 'Geography', 'department_id' => $department->id]);

        $this->actingAs($vp->user)->get('/vp_academic/subjects')
            ->assertOk()
            ->assertSee('Subject catalogue')
            ->assertSee('GEO9');
    }

    public function test_registrar_cannot_manage_the_subject_catalogue(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');

        $this->actingAs($registrar->user)->post('/vp_academic/subjects', [
            'code' => 'HIS9', 'name' => 'History', 'department_id' => $department->id,
        ])->assertForbidden();
    }
}
