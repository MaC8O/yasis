<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_admin_can_set_a_new_password_for_a_user(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $response = $this->actingAs($admin->user)->post(route('admin.users.set-password', $teacher->user), [
            'password' => 'NewStrongPass123',
            'password_confirmation' => 'NewStrongPass123',
        ]);

        $response->assertRedirect();

        $this->post('/logout');

        $login = $this->post('/login', ['email' => 'teacher@test.local', 'password' => 'NewStrongPass123']);
        $login->assertRedirect('/teacher/dashboard');
    }

    public function test_set_password_requires_confirmation_and_minimum_length(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $response = $this->actingAs($admin->user)->post(route('admin.users.set-password', $teacher->user), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_admin_can_hard_delete_a_user_with_no_dependent_records(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $teacherId = $teacher->user->id;

        $response = $this->actingAs($admin->user)->delete(route('admin.users.destroy', $teacher->user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $teacherId]);
        $this->assertDatabaseMissing('staff_profiles', ['id' => $teacherId]);
    }

    public function test_deleting_a_user_with_dependent_records_anonymizes_instead_of_removing(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        AuditLog::create([
            'user_id' => $teacher->user->id,
            'role' => 'teacher',
            'action' => 'Logged in',
            'entity_type' => 'User',
            'entity_id' => $teacher->user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin->user)->delete(route('admin.users.destroy', $teacher->user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $teacher->user->id, 'status' => 'Inactive']);

        $teacher->user->refresh();
        $this->assertStringContainsString('Erased User', $teacher->user->name);
        $this->assertStringContainsString('erased-', $teacher->user->email);

        // The audit trail still resolves to a (now anonymized) row rather than a dangling reference.
        $this->assertDatabaseHas('audit_logs', ['user_id' => $teacher->user->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $response = $this->actingAs($admin->user)->delete(route('admin.users.destroy', $admin->user));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $admin->user->id]);
    }

}
