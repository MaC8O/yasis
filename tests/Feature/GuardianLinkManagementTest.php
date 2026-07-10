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
 * §7.4 Registrar Guardians: edit contact, link/unlink students, set primary.
 * Invariant (§4.1): exactly one primary guardian per student.
 */
class GuardianLinkManagementTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeGuardian(string $email): Guardian
    {
        $user = User::create([
            'name' => 'Guardian '.$email,
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'Active',
        ]);
        $user->assignRole('guardian');

        return Guardian::create(['user_id' => $user->id, 'relationship' => 'Parent', 'phone' => '0912']);
    }

    private function makeStudent(int $departmentId): Student
    {
        return Student::create([
            'student_id_number' => 'S-'.fake()->unique()->numberBetween(1000, 99999),
            'first_name' => 'Aye',
            'last_name' => 'Min',
            'date_of_birth' => now()->subYears(14),
            'gender' => 'F',
            'department_id' => $departmentId,
            'enrollment_status' => 'Enrolled',
            'admission_date' => now()->subYear(),
        ]);
    }

    public function test_registrar_can_edit_guardian_contact(): void
    {
        $this->seedRoles();
        $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $guardian = $this->makeGuardian('mom@test.local');

        $this->actingAs($registrar->user)->put("/registrar/guardians/{$guardian->id}", [
            'name' => 'Daw Mya',
            'email' => 'mya@test.local',
            'relationship' => 'Mother',
            'phone' => '09-777-1234',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $guardian->user_id, 'name' => 'Daw Mya', 'email' => 'mya@test.local']);
        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'relationship' => 'Mother', 'phone' => '09-777-1234']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Guardian', 'entity_id' => $guardian->id, 'user_id' => $registrar->user->id]);
    }

    public function test_guardian_email_cannot_collide_with_another_user(): void
    {
        $this->seedRoles();
        $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $guardian = $this->makeGuardian('mom@test.local');
        $this->makeGuardian('dad@test.local');

        $this->actingAs($registrar->user)->put("/registrar/guardians/{$guardian->id}", [
            'name' => 'Daw Mya',
            'email' => 'dad@test.local',
        ])->assertSessionHasErrors('email');
    }

    public function test_setting_a_new_primary_flips_the_previous_primary_off(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');
        $dad = $this->makeGuardian('dad@test.local');
        $mom->students()->attach($student->id, ['is_primary' => true]);
        $dad->students()->attach($student->id, ['is_primary' => false]);

        $this->actingAs($registrar->user)
            ->post("/registrar/guardians/{$dad->id}/links/{$student->id}/primary")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_guardian', ['student_id' => $student->id, 'guardian_id' => $dad->id, 'is_primary' => true]);
        $this->assertDatabaseHas('student_guardian', ['student_id' => $student->id, 'guardian_id' => $mom->id, 'is_primary' => false]);
    }

    public function test_linking_with_primary_flag_demotes_the_existing_primary(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');
        $dad = $this->makeGuardian('dad@test.local');
        $mom->students()->attach($student->id, ['is_primary' => true]);

        $this->actingAs($registrar->user)->post("/registrar/guardians/{$dad->id}/link", [
            'student_id_number' => $student->student_id_number,
            'is_primary' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_guardian', ['student_id' => $student->id, 'guardian_id' => $dad->id, 'is_primary' => true]);
        $this->assertDatabaseHas('student_guardian', ['student_id' => $student->id, 'guardian_id' => $mom->id, 'is_primary' => false]);
    }

    public function test_first_linked_guardian_becomes_primary_automatically(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');

        $this->actingAs($registrar->user)->post("/registrar/guardians/{$mom->id}/link", [
            'student_id_number' => $student->student_id_number,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_guardian', ['student_id' => $student->id, 'guardian_id' => $mom->id, 'is_primary' => true]);
    }

    public function test_cannot_unlink_the_only_guardian_of_a_student(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');
        $mom->students()->attach($student->id, ['is_primary' => true]);

        $this->actingAs($registrar->user)
            ->delete("/registrar/guardians/{$mom->id}/links/{$student->id}")
            ->assertSessionHasErrors('link');

        $this->assertDatabaseHas('student_guardian', ['student_id' => $student->id, 'guardian_id' => $mom->id]);
    }

    public function test_unlinking_the_primary_promotes_the_remaining_guardian(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');
        $dad = $this->makeGuardian('dad@test.local');
        $mom->students()->attach($student->id, ['is_primary' => true]);
        $dad->students()->attach($student->id, ['is_primary' => false]);

        $this->actingAs($registrar->user)
            ->delete("/registrar/guardians/{$mom->id}/links/{$student->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('student_guardian', ['student_id' => $student->id, 'guardian_id' => $mom->id]);
        $this->assertDatabaseHas('student_guardian', ['student_id' => $student->id, 'guardian_id' => $dad->id, 'is_primary' => true]);
    }

    public function test_unlinking_a_student_not_linked_to_the_guardian_is_a_404(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');

        $this->actingAs($registrar->user)
            ->delete("/registrar/guardians/{$mom->id}/links/{$student->id}")
            ->assertNotFound();
    }

    public function test_other_roles_cannot_manage_guardian_links(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $teacher = $this->makeStaff('teacher', 'Teacher');
        $student = $this->makeStudent($department->id);
        $mom = $this->makeGuardian('mom@test.local');
        $mom->students()->attach($student->id, ['is_primary' => true]);

        $this->actingAs($teacher->user)
            ->post("/registrar/guardians/{$mom->id}/links/{$student->id}/primary")
            ->assertForbidden();
    }
}
