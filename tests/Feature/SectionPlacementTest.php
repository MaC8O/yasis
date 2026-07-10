<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §7.5 Registrar Sections: the Registrar creates sections, assigns the
 * homeroom teacher (must hold the Teacher role), and places students.
 */
class SectionPlacementTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeStudent(int $departmentId, string $first = 'Aye', string $last = 'Min'): Student
    {
        return Student::create([
            'student_id_number' => 'S-'.fake()->unique()->numberBetween(1000, 99999),
            'first_name' => $first,
            'last_name' => $last,
            'date_of_birth' => now()->subYears(14),
            'gender' => 'M',
            'department_id' => $departmentId,
            'enrollment_status' => 'Enrolled',
            'admission_date' => now()->subYear(),
        ]);
    }

    public function test_registrar_can_place_students_into_a_section(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $studentA = $this->makeStudent($department->id, 'Aye', 'Min');
        $studentB = $this->makeStudent($department->id, 'Htoo', 'Paw');

        $this->actingAs($registrar->user)
            ->post("/registrar/sections/{$section->id}/enroll", ['student_ids' => [$studentA->id, $studentB->id]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enrollments', ['student_id' => $studentA->id, 'section_id' => $section->id, 'status' => 'Active']);
        $this->assertDatabaseHas('enrollments', ['student_id' => $studentB->id, 'section_id' => $section->id, 'status' => 'Active']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Enrollment', 'user_id' => $registrar->user->id]);
    }

    public function test_a_student_already_placed_this_year_is_refused(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $sectionA = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $sectionB = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-B', 'capacity' => 35]);
        $student = $this->makeStudent($department->id);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $sectionA->id, 'status' => 'Active']);

        $this->actingAs($registrar->user)
            ->post("/registrar/sections/{$sectionB->id}/enroll", ['student_ids' => [$student->id]])
            ->assertSessionHasErrors('student_ids');

        $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id, 'section_id' => $sectionB->id]);
    }

    public function test_placement_beyond_capacity_is_refused_and_nothing_is_written(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 1]);
        $studentA = $this->makeStudent($department->id, 'Aye', 'Min');
        $studentB = $this->makeStudent($department->id, 'Htoo', 'Paw');

        $this->actingAs($registrar->user)
            ->post("/registrar/sections/{$section->id}/enroll", ['student_ids' => [$studentA->id, $studentB->id]])
            ->assertSessionHasErrors('student_ids');

        $this->assertSame(0, Enrollment::where('section_id', $section->id)->count());
    }

    public function test_a_non_active_student_cannot_be_placed(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $student = $this->makeStudent($department->id);
        $student->update(['enrollment_status' => 'Transferred']);

        $this->actingAs($registrar->user)
            ->post("/registrar/sections/{$section->id}/enroll", ['student_ids' => [$student->id]])
            ->assertSessionHasErrors('student_ids');

        $this->assertSame(0, Enrollment::where('section_id', $section->id)->count());
    }

    public function test_homeroom_teacher_must_hold_the_teacher_role(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');

        $this->actingAs($registrar->user)->post('/registrar/sections', [
            'name' => 'Grade 9-A',
            'department_id' => $department->id,
            'homeroom_teacher_id' => $hr->id,
            'capacity' => 35,
        ])->assertSessionHasErrors('homeroom_teacher_id');

        $this->assertDatabaseMissing('sections', ['name' => 'Grade 9-A']);

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-B', 'capacity' => 35]);

        $this->actingAs($registrar->user)->put("/registrar/sections/{$section->id}", [
            'homeroom_teacher_id' => $hr->id,
            'capacity' => 35,
        ])->assertSessionHasErrors('homeroom_teacher_id');

        $this->assertNull($section->fresh()->homeroom_teacher_id);
    }

    public function test_section_name_must_be_unique_per_year_and_department(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);

        $this->actingAs($registrar->user)->post('/registrar/sections', [
            'name' => 'Grade 9-A',
            'department_id' => $department->id,
            'capacity' => 35,
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Section::where('name', 'Grade 9-A')->count());
    }

    public function test_sections_page_lists_unplaced_students(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $placed = $this->makeStudent($department->id, 'Aye', 'Min');
        Enrollment::create(['student_id' => $placed->id, 'section_id' => $section->id, 'status' => 'Active']);
        $this->makeStudent($department->id, 'Htoo', 'Paw');

        $this->actingAs($registrar->user)->get('/registrar/sections')
            ->assertOk()
            ->assertSee('Place students')
            ->assertSee('Htoo Paw')
            ->assertDontSee('Aye Min');
    }

    public function test_only_the_registrar_can_place_students(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $teacher = $this->makeStaff('teacher', 'Teacher');
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $student = $this->makeStudent($department->id);

        $this->actingAs($teacher->user)
            ->post("/registrar/sections/{$section->id}/enroll", ['student_ids' => [$student->id]])
            ->assertForbidden();
    }
}
