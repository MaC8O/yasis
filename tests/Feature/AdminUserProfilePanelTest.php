<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class AdminUserProfilePanelTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_index_shows_the_panel_for_the_first_user_by_default(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $this->actingAs($admin->user)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User information', false)
            ->assertSee('User #'.$admin->user->id)
            ->assertSee('@dblclick="open(\''.route('admin.users.edit', $admin->user).'\')"', false);
    }

    public function test_index_shows_the_panel_for_the_selected_user(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local', ['staff_id_number' => 'T-9001']);

        $this->actingAs($admin->user)->get(route('admin.users.index', ['selected' => $teacher->user->id]))
            ->assertOk()
            ->assertSee('User #'.$teacher->user->id)
            ->assertSee('T-9001');
    }

    public function test_panel_endpoint_renders_profile_security_and_activity(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $teacher->user->update(['phone' => '09-123456', 'failed_login_attempts' => 2]);

        AuditLog::create([
            'user_id' => $teacher->user->id, 'role' => 'teacher', 'action' => 'Entered grades',
            'entity_type' => 'Grade', 'entity_id' => 1, 'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $admin->user->id, 'role' => 'admin', 'action' => 'Edited user',
            'entity_type' => 'User', 'entity_id' => $teacher->user->id, 'created_at' => now(),
        ]);

        $this->actingAs($admin->user)->get(route('admin.users.panel', $teacher->user))
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('tel:09-123456', false)
            ->assertSee('2 / ')
            ->assertSee('Entered grades')
            ->assertSee('Edited user')
            ->assertSee(route('admin.users.destroy', $teacher->user), false);
    }

    public function test_panel_hides_delete_for_the_signed_in_admin(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $this->actingAs($admin->user)->get(route('admin.users.panel', $admin->user))
            ->assertOk()
            ->assertDontSee('Delete account');
    }

    public function test_non_admins_cannot_load_the_panel(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $this->actingAs($teacher->user)->get(route('admin.users.panel', $teacher->user))
            ->assertForbidden();
    }
}
