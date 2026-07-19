<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §3.8 read-access logging: opening an individual sensitive record (student PII,
 * guardian contact, student financials) is recorded in the audit trail.
 */
class AuditReadAccessTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeStudent(): Student
    {
        $department = $this->seedDepartment();

        return Student::create([
            'student_id_number' => 'YAS-0001',
            'name' => 'Aye Chan',
            'admission_date' => now()->subYear(),
            'department_id' => $department->id,
            'enrollment_status' => 'Enrolled',
        ]);
    }

    public function test_viewing_a_student_record_is_logged(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent();

        $this->actingAs($registrar->user)->get(route('registrar.students.show', $student))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $registrar->user->id,
            'action' => 'Viewed student record',
            'entity_type' => 'Student',
            'entity_id' => $student->id,
        ]);
    }

    public function test_viewing_a_guardian_record_is_logged(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');

        $guardianUser = User::create(['name' => 'Daw Hla', 'email' => 'g@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $guardianUser->assignRole('guardian');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother', 'phone' => '123']);

        $this->actingAs($registrar->user)->get(route('registrar.guardians.show', $guardian))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $registrar->user->id,
            'action' => 'Viewed guardian record',
            'entity_type' => 'Guardian',
            'entity_id' => $guardian->id,
        ]);
    }

    public function test_viewing_a_student_financial_record_is_logged(): void
    {
        $this->seedRoles();
        $treasurer = $this->makeStaff('treasurer', 'Treasurer');
        $student = $this->makeStudent();

        $this->actingAs($treasurer->user)->get(route('treasurer.records.show', $student))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $treasurer->user->id,
            'action' => 'Viewed student financial record',
            'entity_type' => 'Student',
            'entity_id' => $student->id,
        ]);
    }

    public function test_list_pages_are_not_logged(): void
    {
        $this->seedRoles();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $this->makeStudent();

        $this->actingAs($registrar->user)->get(route('registrar.students.index'))->assertOk();

        // Browsing the roster must not flood the trail — only individual-record views are logged.
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Viewed student record']);
    }
}
