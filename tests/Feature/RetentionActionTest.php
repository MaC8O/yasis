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
 * §6.2 Data Retention: an Admin actions an erasure/retention request against a
 * named student or guardian with a reason. PII is scrubbed, portal access is
 * revoked, nothing is hard-deleted, and the action is audited (§15.8).
 */
class RetentionActionTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeStudent(int $departmentId, ?User $portalUser = null): Student
    {
        return Student::create([
            'user_id' => $portalUser?->id,
            'student_id_number' => 'YAS-2026-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Aye Min',
            'date_of_birth' => now()->subYears(14),
            'gender' => 'F',
            'religious_background' => 'Buddhist',
            'department_id' => $departmentId,
            'enrollment_status' => 'Enrolled',
            'admission_date' => now()->subYear(),
        ]);
    }

    private function makeGuardian(string $email): Guardian
    {
        $user = User::create([
            'name' => 'Daw Mya', 'email' => $email,
            'password' => Hash::make('password'), 'status' => 'Active',
        ]);
        $user->assignRole('guardian');

        return Guardian::create(['user_id' => $user->id, 'relationship' => 'Mother', 'phone' => '0911']);
    }

    public function test_admin_can_erase_a_student_record(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $admin = $this->makeStaff('admin', 'Admin');

        $portalUser = User::create([
            'name' => 'Aye Min', 'email' => 'aye@test.local',
            'password' => Hash::make('password'), 'status' => 'Active',
        ]);
        $portalUser->assignRole('student');
        $student = $this->makeStudent($department->id, $portalUser);
        $originalId = $student->student_id_number;

        $this->actingAs($admin->user)->post('/admin/retention-actions', [
            'subject_type' => 'student',
            'identifier' => $originalId,
            'reason' => 'Family erasure request',
        ])->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame('ERASED-'.$student->id, $student->student_id_number);
        $this->assertSame('Erased Record '.$student->id, $student->name);
        $this->assertNull($student->date_of_birth);
        $this->assertNull($student->gender);
        $this->assertNull($student->religious_background);
        $this->assertSame('Dropped', $student->enrollment_status);

        $portalUser->refresh();
        $this->assertSame('Inactive', $portalUser->status);
        $this->assertStringNotContainsString('aye@test.local', $portalUser->email);

        $this->assertDatabaseHas('students', ['id' => $student->id]); // never hard-deleted
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Student', 'entity_id' => $student->id, 'user_id' => $admin->user->id,
            'action' => 'Retention erasure of student record: Family erasure request',
        ]);
    }

    public function test_admin_can_erase_a_guardian_with_no_dependent_links(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $admin = $this->makeStaff('admin', 'Admin');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');
        $dad = $this->makeGuardian('dad@test.local');
        $mom->students()->attach($student->id, ['is_primary' => true]);
        $dad->students()->attach($student->id, ['is_primary' => false]);

        $this->actingAs($admin->user)->post('/admin/retention-actions', [
            'subject_type' => 'guardian',
            'identifier' => 'dad@test.local',
            'reason' => 'Retention period lapsed',
        ])->assertSessionHasNoErrors();

        $dad->refresh();
        $this->assertNull($dad->phone);
        $this->assertNull($dad->relationship);
        $this->assertSame(0, $dad->students()->count());
        $this->assertSame('Inactive', $dad->user->fresh()->status);

        // The other guardian's link is untouched.
        $this->assertSame(1, $mom->students()->count());
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Guardian', 'entity_id' => $dad->id]);
    }

    public function test_erasing_the_only_guardian_of_an_enrolled_student_is_refused(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $admin = $this->makeStaff('admin', 'Admin');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');
        $mom->students()->attach($student->id, ['is_primary' => true]);

        $this->actingAs($admin->user)->post('/admin/retention-actions', [
            'subject_type' => 'guardian',
            'identifier' => 'mom@test.local',
            'reason' => 'Erasure request',
        ])->assertSessionHasErrors('identifier');

        $this->assertSame('Active', $mom->user->fresh()->status);
        $this->assertSame(1, $mom->students()->count());
    }

    public function test_unknown_identifier_is_rejected_with_an_error(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        $this->actingAs($admin->user)->post('/admin/retention-actions', [
            'subject_type' => 'student',
            'identifier' => 'YAS-0000-0000',
            'reason' => 'Erasure request',
        ])->assertSessionHasErrors('identifier');
    }

    public function test_reason_is_required(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $admin = $this->makeStaff('admin', 'Admin');
        $student = $this->makeStudent($department->id);

        $this->actingAs($admin->user)->post('/admin/retention-actions', [
            'subject_type' => 'student',
            'identifier' => $student->student_id_number,
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->assertSame('Aye Min', $student->fresh()->name);
    }

    public function test_non_admins_cannot_action_retention_requests(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent($department->id);

        $this->actingAs($registrar->user)->post('/admin/retention-actions', [
            'subject_type' => 'student',
            'identifier' => $student->student_id_number,
            'reason' => 'Erasure request',
        ])->assertForbidden();
    }
}
