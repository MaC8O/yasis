<?php

namespace Tests\Feature;

use App\Exceptions\AccountChangeRefused;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserAccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\MakesPortalAccounts;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/** Admin → User Management: create, edit, deactivate and reactivate, for any role. */
class AdminAccountManagementTest extends TestCase
{
    use MakesPortalAccounts, RefreshDatabase, SeedsCoreData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local')->user;
    }

    private function auditFor(string $action, User $target): ?AuditLog
    {
        return AuditLog::where('action', $action)->where('entity_type', 'User')->where('entity_id', $target->id)->first();
    }

    public function test_admin_can_create_a_staff_account_with_an_audit_entry(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Daw Hla',
            'email' => 'hla@test.local',
            'role' => 'principal',
            'staff_id_number' => 'P-001',
            'joined_date' => now()->subMonth()->toDateString(),
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'hla@test.local')->firstOrFail();
        $this->assertTrue($user->hasRole('principal'));
        $this->assertSame('Pending', $user->status);
        $this->assertDatabaseHas('staff_profiles', ['id' => $user->id, 'role_type' => 'Principal']);

        $audit = $this->auditFor('Created user', $user);
        $this->assertSame('admin', $audit->role);
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(['role' => 'principal', 'status' => 'Pending'], $audit->details);

        // Exactly one setup e-mail (the controller used to send a second one).
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_admin_can_create_guardian_and_student_accounts(): void
    {
        Notification::fake();

        foreach (['guardian', 'student'] as $role) {
            $this->actingAs($this->admin)->post(route('admin.users.store'), [
                'name' => ucfirst($role).' One',
                'email' => "{$role}1@test.local",
                'role' => $role,
            ])->assertRedirect(route('admin.users.index'));

            $user = User::where('email', "{$role}1@test.local")->firstOrFail();
            $this->assertTrue($user->hasRole($role));
            $this->assertNotNull($this->auditFor('Created user', $user));
        }
    }

    public function test_create_rejects_an_unknown_role(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Nobody', 'email' => 'nobody@test.local', 'role' => 'superuser',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'nobody@test.local']);
    }

    public function test_admin_can_edit_profile_and_role_and_the_audit_records_what_changed(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;

        $this->actingAs($this->admin)->put(route('admin.users.update', $teacher), [
            'name' => 'U Kyaw Renamed',
            'email' => 'teacher@test.local',
            'role' => 'treasurer',
        ])->assertRedirect();

        $teacher->refresh();
        $this->assertSame('U Kyaw Renamed', $teacher->name);
        $this->assertTrue($teacher->hasRole('treasurer'));
        $this->assertFalse($teacher->hasRole('teacher'));
        $this->assertDatabaseHas('staff_profiles', ['id' => $teacher->id, 'role_type' => 'Treasurer']);

        $audit = $this->auditFor('Edited user', $teacher);
        $this->assertSame('admin', $audit->role);
        $this->assertSame(['from' => 'teacher', 'to' => 'treasurer'], $audit->details['role']);
        $this->assertSame('U Kyaw Renamed', $audit->details['name']['to']);
    }

    public function test_admin_can_deactivate_a_user_who_then_cannot_sign_in_and_is_not_deleted(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $teacher))
            ->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'status' => 'Inactive']);
        $this->assertDatabaseHas('staff_profiles', ['id' => $teacher->id]);

        $audit = $this->auditFor('Deactivated user', $teacher);
        $this->assertSame('admin', $audit->role);
        $this->assertSame(['status' => ['from' => 'Active', 'to' => 'Inactive']], $audit->details);

        $this->post('/logout');
        $this->post('/login', ['email' => 'teacher@test.local', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_session_opened_before_deactivation_is_ended_on_the_next_request(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk();

        app(UserAccountService::class)->deactivate($this->admin, $teacher);

        // The next request loads the account fresh from the database, as a real session does.
        $this->actingAs($teacher->fresh())->get('/teacher/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_pending_account_can_be_deactivated(): void
    {
        $pending = $this->makeAccount('guardian', 'pending@test.local', 'Pending');

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $pending))->assertRedirect();

        $this->assertSame('Inactive', $pending->fresh()->status);
    }

    public function test_admin_can_reactivate_an_inactive_user(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $teacher->forceFill(['status' => 'Inactive'])->save();

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $teacher))
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame('Active', $teacher->fresh()->status);
        $audit = $this->auditFor('Reactivated user', $teacher);
        $this->assertSame('admin', $audit->role);
        $this->assertSame(['status' => ['from' => 'Inactive', 'to' => 'Active']], $audit->details);

        $this->post('/logout');
        $this->post('/login', ['email' => 'teacher@test.local', 'password' => 'password'])
            ->assertRedirect('/teacher/dashboard');
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $this->admin))
            ->assertRedirect()->assertSessionHas('warning');

        $this->assertSame('Active', $this->admin->fresh()->status);
        $this->assertNull($this->auditFor('Deactivated user', $this->admin));
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'teacher',
        ])->assertSessionHas('warning');

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->assertNull($this->auditFor('Edited user', $this->admin));
    }

    public function test_admin_can_deactivate_another_admin_while_an_active_admin_remains(): void
    {
        $second = $this->makeStaff('admin', 'Admin', 'admin2@test.local')->user;

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $second))->assertSessionHas('status');

        $this->assertSame('Inactive', $second->fresh()->status);
    }

    /**
     * Over HTTP the acting Admin is always an active Admin, so the last-admin rule is what
     * protects the race: two Admins deactivate each other at the same moment. The second
     * request passed the session check before the first committed; once the first has
     * committed it would leave no active Admin, and must be refused.
     */
    public function test_deactivating_the_last_active_admin_is_refused(): void
    {
        $second = $this->makeStaff('admin', 'Admin', 'admin2@test.local')->user;
        $accounts = app(UserAccountService::class);

        $accounts->deactivate($this->admin, $second);

        try {
            $accounts->deactivate($second, $this->admin);
            $this->fail('Deactivating the last active Admin should be refused.');
        } catch (AccountChangeRefused $e) {
            $this->assertStringContainsString('only active Admin', $e->getMessage());
        }

        $this->assertSame('Active', $this->admin->fresh()->status);
        $this->assertNull($this->auditFor('Deactivated user', $this->admin));
    }

    public function test_demoting_the_last_active_admin_is_refused(): void
    {
        $second = $this->makeStaff('admin', 'Admin', 'admin2@test.local')->user;
        $accounts = app(UserAccountService::class);
        $accounts->deactivate($this->admin, $second);

        $this->expectException(AccountChangeRefused::class);
        $accounts->update($second, $this->admin, ['name' => 'Admin', 'email' => 'admin@test.local', 'role' => 'teacher']);
    }

    public function test_a_failed_audit_write_rolls_the_status_change_back(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $this->mock(AuditService::class, fn ($mock) => $mock->shouldReceive('log')->andThrow(new RuntimeException('audit store down')));

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $teacher))->assertServerError();

        $this->assertSame('Active', $teacher->fresh()->status);
    }

    /** The stated exception to "never hard delete": data-retention erasure. */
    public function test_erasure_delete_asks_for_confirmation_and_is_audited_either_way(): void
    {
        $unreferenced = $this->makeAccount('guardian', 'gone@test.local');
        $referenced = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        app(AuditService::class)->log($referenced, 'Logged in', 'User', $referenced->id);

        $this->actingAs($this->admin)->get(route('admin.users.panel', $unreferenced))
            ->assertSee('data-confirm="Delete', false)
            ->assertSee('onsubmit="return confirm(this.dataset.confirm);"', false);

        $this->delete(route('admin.users.destroy', $unreferenced))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $unreferenced->id]);
        $deleted = AuditLog::where('action', 'Deleted user')->where('entity_id', $unreferenced->id)->firstOrFail();
        $this->assertSame('admin', $deleted->role);
        $this->assertSame('deleted', $deleted->details['outcome']);

        $this->delete(route('admin.users.destroy', $referenced))->assertRedirect(route('admin.users.index'));
        $this->assertSame('Inactive', $referenced->fresh()->status);
        $erased = AuditLog::where('action', 'like', 'Erased user PII%')->where('entity_id', $referenced->id)->firstOrFail();
        $this->assertSame('admin', $erased->role);
    }

    public function test_reactivating_a_pending_account_is_refused(): void
    {
        $pending = $this->makeAccount('guardian', 'pending@test.local', 'Pending');

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $pending))->assertSessionHas('warning');

        $this->assertSame('Pending', $pending->fresh()->status);
    }
}
