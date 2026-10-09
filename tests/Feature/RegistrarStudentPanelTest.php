<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class RegistrarStudentPanelTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeStudent(): Student
    {
        $department = $this->seedDepartment('High School');

        return Student::create([
            'student_id_number' => 'YAS-2026-7001', 'name' => 'Saw Htoo Aung',
            'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled',
        ]);
    }

    public function test_index_shows_an_empty_panel_and_logs_nothing_until_a_student_is_chosen(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent();

        $this->actingAs($registrar->user)->get(route('registrar.students.index'))
            ->assertOk()
            ->assertSee('No student selected')
            ->assertSee(route('registrar.students.panel', $student), false)
            ->assertSee('@dblclick="open(\''.route('registrar.students.show', $student).'\')"', false);

        $this->assertSame(0, AuditLog::where('entity_type', 'Student')->count());
    }

    public function test_index_with_selected_renders_the_panel_and_audits_the_view(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent();

        $this->actingAs($registrar->user)->get(route('registrar.students.index', ['selected' => $student->id]))
            ->assertOk()
            ->assertSee('Student information', false)
            ->assertDontSee('No student selected');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $registrar->user->id, 'action' => 'Viewed student summary', 'entity_type' => 'Student', 'entity_id' => $student->id,
        ]);
    }

    public function test_panel_shows_guardian_contact_class_and_attendance(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent();
        $term = $this->seedAcademicCalendar();
        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $student->department_id, 'name' => 'Grade 9-A', 'capacity' => 30]);
        $student->enrollments()->create(['section_id' => $section->id, 'status' => 'Active']);

        $guardianUser = User::create(['name' => 'Daw Hla Myint', 'email' => 'hla@test.local', 'password' => 'x', 'status' => 'Active']);
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother', 'phone' => '09-555']);
        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        foreach (['Present', 'Present', 'Tardy', 'Absent'] as $i => $status) {
            AttendanceRecord::create([
                'student_id' => $student->id, 'section_id' => $section->id, 'term_id' => $term->id,
                'attendance_date' => now()->subDays($i + 1)->toDateString(), 'status' => $status, 'recorded_by' => $registrar->id,
            ]);
        }

        $this->actingAs($registrar->user)->get(route('registrar.students.panel', $student))
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('Grade 9-A')
            ->assertSee('mailto:hla@test.local', false)
            ->assertSee('tel:09-555', false)
            ->assertSee('75%')
            ->assertSee(route('registrar.students.transfer', $student), false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'Viewed student summary', 'entity_id' => $student->id]);
    }

    public function test_non_registrars_cannot_load_the_panel(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher');
        $student = $this->makeStudent();

        $this->actingAs($teacher->user)->get(route('registrar.students.panel', $student))->assertForbidden();
    }
}
