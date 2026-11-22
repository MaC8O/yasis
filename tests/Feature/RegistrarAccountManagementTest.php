<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\MakesPortalAccounts;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Registrar → guardian and student portal accounts: create, deactivate, reactivate. Every
 * other account (staff, Admin, Principal, VP) is out of scope, and the server refuses it
 * whether or not the request came from a form.
 */
class RegistrarAccountManagementTest extends TestCase
{
    use MakesPortalAccounts, RefreshDatabase, SeedsCoreData;

    private User $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local')->user;
    }

    private function auditCount(User $target): int
    {
        return AuditLog::where('entity_type', 'User')->where('entity_id', $target->id)->count();
    }

    public function test_registrar_creates_a_guardian_account_and_any_role_sent_with_the_request_is_ignored(): void
    {
        Notification::fake();

        $this->actingAs($this->registrar)->post(route('registrar.guardians.store'), [
            'name' => 'Daw Mya',
            'email' => 'mya@test.local',
            'relationship' => 'Mother',
            'role' => 'admin', // not a field of this form — a hand-crafted extra
        ])->assertRedirect(route('registrar.guardians.index'));

        $user = User::where('email', 'mya@test.local')->firstOrFail();
        $this->assertSame(['guardian'], $user->getRoleNames()->all());
        $this->assertSame('Pending', $user->status);
        Notification::assertSentTo($user, ResetPassword::class);

        $audit = AuditLog::where('action', 'Added guardian')->firstOrFail();
        $this->assertSame('registrar', $audit->role);
        $this->assertSame($user->id, $audit->details['user_id']);
    }

    public function test_registrar_creates_a_student_portal_login(): void
    {
        Notification::fake();
        $student = $this->makeStudent();

        $this->actingAs($this->registrar)->post(route('registrar.students.login.store', $student), [
            'email' => 'ayemin@test.local',
        ])->assertRedirect()->assertSessionHas('status');

        $user = User::where('email', 'ayemin@test.local')->firstOrFail();
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $this->assertSame('Pending', $user->status);
        $this->assertSame($user->id, $student->fresh()->user_id);
        Notification::assertSentTo($user, ResetPassword::class);

        $audit = AuditLog::where('action', 'Created student portal login')->firstOrFail();
        $this->assertSame('registrar', $audit->role);
        $this->assertSame($user->id, $audit->entity_id);
    }

    public function test_a_student_who_already_has_a_login_cannot_get_a_second_one(): void
    {
        $student = $this->makeStudent($this->makeAccount('student', 'first@test.local'));

        $this->actingAs($this->registrar)->post(route('registrar.students.login.store', $student), [
            'email' => 'second@test.local',
        ])->assertSessionHas('warning');

        $this->assertDatabaseMissing('users', ['email' => 'second@test.local']);
    }

    public function test_registrar_deactivates_and_reactivates_a_guardian_account(): void
    {
        $guardian = $this->makeGuardian('mya@test.local')->user;

        $this->actingAs($this->registrar)->post(route('registrar.accounts.deactivate', $guardian))->assertSessionHas('status');
        $this->assertSame('Inactive', $guardian->fresh()->status);
        $deactivated = AuditLog::where('action', 'Deactivated user')->where('entity_id', $guardian->id)->firstOrFail();
        $this->assertSame('registrar', $deactivated->role);

        $this->post('/logout');
        $this->post('/login', ['email' => 'mya@test.local', 'password' => 'password'])->assertSessionHasErrors('email');

        $this->actingAs($this->registrar)->post(route('registrar.accounts.reactivate', $guardian))->assertSessionHas('status');
        $this->assertSame('Active', $guardian->fresh()->status);
        $reactivated = AuditLog::where('action', 'Reactivated user')->where('entity_id', $guardian->id)->firstOrFail();
        $this->assertSame('registrar', $reactivated->role);
    }

    public function test_registrar_deactivates_and_reactivates_a_student_account(): void
    {
        $student = $this->makeAccount('student', 'ayemin@test.local');
        $this->makeStudent($student);

        $this->actingAs($this->registrar)->post(route('registrar.accounts.deactivate', $student))->assertSessionHas('status');
        $this->assertSame('Inactive', $student->fresh()->status);

        $this->post(route('registrar.accounts.reactivate', $student))->assertSessionHas('status');
        $this->assertSame('Active', $student->fresh()->status);
        $this->assertSame(2, $this->auditCount($student));
    }

    public function test_registrar_is_refused_on_every_staff_and_leadership_account(): void
    {
        $targets = [
            'teacher' => 'Teacher', 'admin' => 'Admin', 'principal' => 'Principal',
            'vp_academic' => 'VP_Academic', 'hr_office' => 'HR_Office', 'treasurer' => 'Treasurer',
        ];

        foreach ($targets as $role => $roleType) {
            $staff = $this->makeStaff($role, $roleType, "{$role}@test.local")->user;

            $this->actingAs($this->registrar)->post(route('registrar.accounts.deactivate', $staff))->assertForbidden();
            $this->assertSame('Active', $staff->fresh()->status, "{$role} account must not change");
            $this->assertSame(0, $this->auditCount($staff), "no audit entry for a refused change to {$role}");
        }

        // Another Registrar is staff too.
        $otherRegistrar = $this->makeStaff('registrar', 'Registrar', 'registrar2@test.local')->user;
        $this->actingAs($this->registrar)->post(route('registrar.accounts.deactivate', $otherRegistrar))->assertForbidden();
    }

    public function test_registrar_cannot_reactivate_a_staff_account(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $teacher->forceFill(['status' => 'Inactive'])->save();

        $this->actingAs($this->registrar)->post(route('registrar.accounts.reactivate', $teacher))->assertForbidden();

        $this->assertSame('Inactive', $teacher->fresh()->status);
    }

    public function test_registrar_cannot_deactivate_a_teacher_who_is_also_a_guardian(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $teacher->assignRole('guardian');

        $this->actingAs($this->registrar)->post(route('registrar.accounts.deactivate', $teacher))->assertForbidden();

        $this->assertSame('Active', $teacher->fresh()->status);
    }

    public function test_registrar_cannot_reach_the_admin_user_management_endpoints_directly(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $this->actingAs($this->registrar);

        $this->post(route('admin.users.store'), [
            'name' => 'New Teacher', 'email' => 'new-teacher@test.local', 'role' => 'teacher',
            'staff_id_number' => 'T-999', 'joined_date' => now()->toDateString(),
        ])->assertForbidden();
        $this->put(route('admin.users.update', $teacher), [
            'name' => 'Hijacked', 'email' => 'teacher@test.local', 'role' => 'admin',
        ])->assertForbidden();
        $this->post(route('admin.users.deactivate', $teacher))->assertForbidden();
        $this->post(route('admin.users.reactivate', $teacher))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new-teacher@test.local']);
        $teacher->refresh();
        $this->assertSame('Active', $teacher->status);
        $this->assertFalse($teacher->hasRole('admin'));
    }

    public function test_student_page_offers_create_login_then_deactivate(): void
    {
        $student = $this->makeStudent();
        $this->actingAs($this->registrar);

        $this->get(route('registrar.students.show', $student))->assertOk()
            ->assertSee(route('registrar.students.login.store', $student), false);

        $student->update(['user_id' => $this->makeAccount('student', 'ayemin@test.local')->id]);

        $this->get(route('registrar.students.show', $student))->assertOk()
            ->assertSee(route('registrar.accounts.deactivate', $student->user_id), false)
            ->assertDontSee(route('registrar.students.login.store', $student), false);
    }

    public function test_guardian_page_offers_reactivate_for_an_inactive_account(): void
    {
        $guardian = $this->makeGuardian('mya@test.local', 'Inactive');

        $this->actingAs($this->registrar)->get(route('registrar.guardians.show', $guardian))->assertOk()
            ->assertSee(route('registrar.accounts.reactivate', $guardian->user), false)
            ->assertDontSee(route('registrar.accounts.deactivate', $guardian->user), false);
    }

    public function test_the_service_refuses_a_registrar_creating_any_staff_role_even_without_a_form(): void
    {
        $provisioning = app(UserProvisioningService::class);

        foreach (['teacher', 'admin', 'principal', 'vp_academic', 'hr_office', 'treasurer', 'registrar'] as $role) {
            try {
                $provisioning->provisionAccount(['name' => 'X', 'email' => "{$role}-new@test.local"], $role, $this->registrar, invite: false);
                $this->fail("Registrar should not be able to create a {$role} account.");
            } catch (AuthorizationException) {
                $this->assertDatabaseMissing('users', ['email' => "{$role}-new@test.local"]);
            }
        }
    }
}
