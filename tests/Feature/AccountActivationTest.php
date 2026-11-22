<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\MakesPortalAccounts;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Account status transitions (Admin for any role, Registrar for guardian and student accounts):
 *
 *   Pending  → Active    Activate: temporary password shown once, must change it at first sign-in
 *   Pending  → Pending   Resend invite
 *   Active   → Inactive  Deactivate
 *   Pending  → Inactive  Deactivate
 *   Inactive → Active    Reactivate, when a person was ever given a working password
 *   Inactive → Pending   Reactivate, when nobody ever was; the setup link is e-mailed again
 *
 * Erased accounts and HR staff records without portal access are never (re)activated.
 */
class AccountActivationTest extends TestCase
{
    use MakesPortalAccounts, RefreshDatabase, SeedsCoreData;

    private User $admin;

    private User $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local')->user;
        $this->registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local')->user;
    }

    private function audits(User $target, ?string $action = null)
    {
        return AuditLog::where('entity_type', 'User')->where('entity_id', $target->id)
            ->when($action, fn ($q) => $q->where('action', $action))->get();
    }

    /** The temporary password as shown on the Activate response. */
    private function shownPassword(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('#<code id="temporary-password"[^>]*>([^<]+)</code>#', $response->getContent(), $m), 'The response shows the temporary password');

        return trim($m[1]);
    }

    // ── Pending → Active (Activate) ─────────────────────────────────────────

    public function test_admin_activates_a_pending_account_with_a_temporary_password_shown_once(): void
    {
        $pending = $this->makeAccount('teacher', 'new.teacher@test.local', 'Pending');
        // An outstanding setup link from the original invite.
        Password::createToken($pending);
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', $pending->email)->count());

        $response = $this->actingAs($this->admin)->post(route('admin.users.activate', $pending));

        $response->assertOk()->assertSee('This is the only time it is shown.');
        $temporary = $this->shownPassword($response);

        $pending->refresh();
        $this->assertSame('Active', $pending->status);
        $this->assertTrue($pending->must_reset_password);
        $this->assertNotNull($pending->password_set_at);
        $this->assertTrue(Hash::check($temporary, $pending->password));
        $this->assertGreaterThanOrEqual(12, strlen($temporary));
        $this->assertMatchesRegularExpression('/[a-z]/', $temporary);
        $this->assertMatchesRegularExpression('/[A-Z]/', $temporary);
        $this->assertMatchesRegularExpression('/[0-9]/', $temporary);
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $pending->email)->count(), 'the old setup link is withdrawn');

        $audit = $this->audits($pending, 'Activated user')->sole();
        $this->assertSame('admin', $audit->role);
        $this->assertSame(['from' => 'Pending', 'to' => 'Active'], $audit->details['status']);
        $this->assertTrue($audit->details['temporary_password_issued']);
    }

    public function test_the_activated_account_must_choose_a_new_password_at_first_sign_in(): void
    {
        $pending = $this->makeAccount('guardian', 'parent@test.local', 'Pending');
        $temporary = $this->shownPassword($this->actingAs($this->admin)->post(route('admin.users.activate', $pending)));

        $this->post(route('logout'));
        $this->post('/login', ['email' => 'parent@test.local', 'password' => $temporary])
            ->assertRedirect(route('password.set'));
    }

    public function test_the_activate_response_is_no_store_and_is_not_a_redirect_or_flash(): void
    {
        $pending = $this->makeAccount('student', 'kid@test.local', 'Pending');

        $response = $this->actingAs($this->admin)->post(route('admin.users.activate', $pending));

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $response->assertSessionMissing('status');
    }

    public function test_the_temporary_password_is_never_in_an_audit_entry_the_session_or_the_log(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
            $logged[] = $e->message.' '.json_encode($e->context);
        });

        $pending = $this->makeAccount('guardian', 'quiet@test.local', 'Pending');
        $response = $this->actingAs($this->registrar)->post(route('registrar.accounts.activate', $pending));
        $temporary = $this->shownPassword($response);

        foreach (AuditLog::all() as $entry) {
            $this->assertStringNotContainsString($temporary, $entry->action.' '.json_encode($entry->details), "audit entry #{$entry->id}");
        }
        $this->assertStringNotContainsString($temporary, serialize($this->app['session.store']->all()), 'session');
        $this->assertStringNotContainsString($temporary, implode("\n", $logged), 'log');
        // And not stored in plain text on the account either.
        $this->assertStringNotContainsString($temporary, json_encode(DB::table('users')->where('id', $pending->id)->first()));
    }

    public function test_a_second_activate_on_the_same_account_is_refused(): void
    {
        $pending = $this->makeAccount('guardian', 'twice@test.local', 'Pending');
        $first = $this->shownPassword($this->actingAs($this->admin)->post(route('admin.users.activate', $pending)));

        // A browser refresh re-sends the same POST.
        $again = $this->actingAs($this->admin)->post(route('admin.users.activate', $pending));

        $again->assertRedirect()->assertSessionHas('warning');
        $this->assertStringNotContainsString($first, $again->getContent());
        $this->assertTrue(Hash::check($first, $pending->fresh()->password), 'the first temporary password still works');
        $this->assertCount(1, $this->audits($pending, 'Activated user'));
    }

    public function test_activate_is_refused_for_an_account_that_is_not_pending(): void
    {
        $active = $this->makeAccount('teacher', 'active@test.local');
        $inactive = $this->makeAccount('teacher', 'inactive@test.local', 'Inactive');

        foreach ([$active, $inactive] as $account) {
            $before = $account->password;
            $this->actingAs($this->admin)->post(route('admin.users.activate', $account))->assertSessionHas('warning');
            $this->assertSame($before, $account->fresh()->password);
            $this->assertCount(0, $this->audits($account, 'Activated user'));
        }
    }

    // ── Pending → Pending (Resend invite) ───────────────────────────────────

    public function test_admin_resends_the_invite_to_a_pending_account(): void
    {
        Notification::fake();
        $pending = $this->makeAccount('teacher', 'invitee@test.local', 'Pending');

        $this->actingAs($this->admin)->post(route('admin.users.reset-password', $pending))->assertSessionHas('status');

        Notification::assertSentTo($pending, ResetPassword::class);
        $this->assertSame('Pending', $pending->fresh()->status);
        $this->assertSame('admin', $this->audits($pending, 'Resent account-setup link')->sole()->role);
    }

    // ── Active → Inactive, Pending → Inactive (Deactivate) ──────────────────

    public function test_deactivating_an_active_account(): void
    {
        $active = $this->makeAccount('teacher', 'leaving@test.local');

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $active))->assertSessionHas('status');

        $this->assertSame('Inactive', $active->fresh()->status);
        $audit = $this->audits($active, 'Deactivated user')->sole();
        $this->assertSame(['from' => 'Active', 'to' => 'Inactive'], $audit->details['status']);
        $this->assertSame('admin', $audit->role);
    }

    public function test_deactivating_a_pending_account(): void
    {
        $pending = $this->makeAccount('guardian', 'never@test.local', 'Pending');

        $this->actingAs($this->registrar)->post(route('registrar.accounts.deactivate', $pending))->assertSessionHas('status');

        $this->assertSame('Inactive', $pending->fresh()->status);
        $audit = $this->audits($pending, 'Deactivated user')->sole();
        $this->assertSame(['from' => 'Pending', 'to' => 'Inactive'], $audit->details['status']);
        $this->assertSame('registrar', $audit->role);
    }

    // ── Inactive → Active / Inactive → Pending (Reactivate) ─────────────────

    public function test_reactivating_an_account_whose_password_was_set_returns_it_to_active(): void
    {
        Notification::fake();
        $inactive = $this->makeAccount('teacher', 'back@test.local', 'Inactive', passwordSet: true);

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $inactive))->assertSessionHas('status');

        $this->assertSame('Active', $inactive->fresh()->status);
        Notification::assertNothingSent();
        $audit = $this->audits($inactive, 'Reactivated user')->sole();
        $this->assertSame(['from' => 'Inactive', 'to' => 'Active'], $audit->details['status']);
        $this->assertSame('admin', $audit->role);
    }

    public function test_reactivating_an_account_that_never_set_a_password_returns_it_to_pending_and_resends_the_invite(): void
    {
        Notification::fake();
        $inactive = $this->makeAccount('guardian', 'unfinished@test.local', 'Inactive', passwordSet: false);

        $this->actingAs($this->registrar)->post(route('registrar.accounts.reactivate', $inactive))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Pending again'));

        $this->assertSame('Pending', $inactive->fresh()->status);
        Notification::assertSentTo($inactive, ResetPassword::class);
        $audit = $this->audits($inactive, 'Reactivated user')->sole();
        $this->assertSame(['from' => 'Inactive', 'to' => 'Pending'], $audit->details['status']);
        $this->assertTrue($audit->details['setup_link_sent']);
        $this->assertSame('registrar', $audit->role);
    }

    public function test_a_pending_account_that_is_deactivated_then_reactivated_is_pending_again(): void
    {
        Notification::fake();
        $pending = $this->makeAccount('student', 'roundtrip@test.local', 'Pending');

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $pending));
        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $pending));

        $this->assertSame('Pending', $pending->fresh()->status);
        Notification::assertSentTo($pending, ResetPassword::class);
    }

    public function test_the_reactivate_button_shows_for_inactive_login_accounts_of_any_role(): void
    {
        foreach (['teacher', 'guardian', 'principal'] as $role) {
            $this->makeAccount($role, "{$role}.inactive@test.local", 'Inactive', passwordSet: $role !== 'guardian');
        }

        $page = $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'Inactive']))->assertOk();

        $this->assertSame(3, substr_count($page->getContent(), '>Reactivate</button>'));
    }

    // ── Accounts that are never (re)activated ──────────────────────────────

    public function test_erased_accounts_show_a_reason_and_reactivate_is_refused(): void
    {
        $byDelete = $this->makeAccount('teacher', 'gone@test.local', 'Inactive');
        $byDelete->forceFill(['name' => "Erased User #{$byDelete->id}", 'email' => "erased-{$byDelete->id}@deleted.yasis.edu"])->save();
        $byRetention = $this->makeAccount('guardian', 'old@test.local', 'Inactive');
        $byRetention->forceFill(['name' => 'Erased Guardian 7', 'email' => 'erased-guardian-7@retention.invalid'])->save();

        $page = $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'Inactive']))->assertOk();
        $this->assertSame(0, substr_count($page->getContent(), '>Reactivate</button>'));
        $this->assertSame(2, substr_count($page->getContent(), '>Erased account</span>'));

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $byDelete))
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'erased'));
        $this->actingAs($this->registrar)->post(route('registrar.accounts.reactivate', $byRetention))->assertSessionHas('warning');

        $this->assertSame('Inactive', $byDelete->fresh()->status);
        $this->assertSame('Inactive', $byRetention->fresh()->status);
        $this->assertCount(0, $this->audits($byDelete, 'Reactivated user'));
        $this->assertCount(0, $this->audits($byRetention, 'Reactivated user'));
    }

    public function test_hr_staff_records_without_portal_access_show_a_reason_and_reactivate_is_refused(): void
    {
        // As HR creates a personnel-only record: no role, placeholder e-mail, created Inactive.
        $record = User::create([
            'name' => 'Daw Nilar', 'email' => 'daw-nilar.s010@internal.yasis.edu',
            'password' => Hash::make('x'), 'status' => 'Inactive',
        ]);

        $page = $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'Inactive']))->assertOk();
        $this->assertStringContainsString('>No portal access</span>', $page->getContent());
        $this->assertStringNotContainsString(route('admin.users.reactivate', $record), $page->getContent());

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $record))
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'without portal access'));
        $this->assertSame('Inactive', $record->fresh()->status);

        // Outside the Registrar's scope altogether.
        $this->actingAs($this->registrar)->post(route('registrar.accounts.reactivate', $record))->assertForbidden();
    }

    // ── Set password (Admin) on Pending accounts ───────────────────────────

    public function test_set_password_is_hidden_and_refused_for_a_pending_account(): void
    {
        $pending = $this->makeAccount('teacher', 'later@test.local', 'Pending');
        $before = $pending->password;

        $this->actingAs($this->admin)->get(route('admin.users.edit', $pending))
            ->assertOk()->assertDontSee(route('admin.users.set-password', $pending))->assertSee('Use');

        $this->actingAs($this->admin)->post(route('admin.users.set-password', $pending), [
            'password' => 'Brand-new-9', 'password_confirmation' => 'Brand-new-9',
        ])->assertSessionHas('warning');

        $this->assertSame($before, $pending->fresh()->password);
        $this->assertSame('Pending', $pending->fresh()->status);
    }

    public function test_set_password_on_an_inactive_account_counts_as_a_known_password(): void
    {
        $inactive = $this->makeAccount('teacher', 'reset.me@test.local', 'Inactive', passwordSet: false);

        $this->actingAs($this->admin)->post(route('admin.users.set-password', $inactive), [
            'password' => 'Brand-new-9', 'password_confirmation' => 'Brand-new-9',
        ])->assertSessionHas('status');

        $this->assertNotNull($inactive->fresh()->password_set_at);
        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $inactive));
        $this->assertSame('Active', $inactive->fresh()->status);
    }

    // ── Registrar ──────────────────────────────────────────────────────────

    public function test_registrar_activates_and_resends_the_invite_for_a_pending_guardian(): void
    {
        Notification::fake();
        $guardian = $this->makeGuardian('parent.pending@test.local', 'Pending');
        $account = $guardian->user;

        $page = $this->actingAs($this->registrar)->get(route('registrar.guardians.show', $guardian))->assertOk();
        $page->assertSee(route('registrar.accounts.resend-invite', $account))->assertSee(route('registrar.accounts.activate', $account));

        $this->actingAs($this->registrar)->post(route('registrar.accounts.resend-invite', $account))->assertSessionHas('status');
        Notification::assertSentTo($account, ResetPassword::class);
        $this->assertSame('registrar', $this->audits($account, 'Resent account-setup link')->sole()->role);

        $response = $this->actingAs($this->registrar)->post(route('registrar.accounts.activate', $account));
        $temporary = $this->shownPassword($response);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $account->refresh();
        $this->assertSame('Active', $account->status);
        $this->assertTrue($account->must_reset_password);
        $this->assertTrue(Hash::check($temporary, $account->password));
        $this->assertSame('registrar', $this->audits($account, 'Activated user')->sole()->role);
    }

    public function test_registrar_activates_a_pending_student_login(): void
    {
        $account = $this->makeAccount('student', 'pupil@test.local', 'Pending');
        $student = $this->makeStudent($account);

        $this->actingAs($this->registrar)->get(route('registrar.students.show', $student))
            ->assertOk()->assertSee(route('registrar.accounts.activate', $account));

        $this->actingAs($this->registrar)->post(route('registrar.accounts.activate', $account))->assertOk();
        $this->assertSame('Active', $account->fresh()->status);
    }

    public function test_registrar_gets_403_on_a_staff_account_for_every_action(): void
    {
        Notification::fake();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user;
        $teacherAndParent = $this->makeStaff('teacher', 'Teacher', 'teacher.parent@test.local')->user;
        $teacherAndParent->assignRole('guardian');

        $actions = ['registrar.accounts.deactivate', 'registrar.accounts.reactivate', 'registrar.accounts.activate', 'registrar.accounts.resend-invite'];

        foreach ([$teacher, $teacherAndParent] as $staff) {
            foreach (['Active', 'Pending', 'Inactive'] as $status) {
                $staff->forceFill(['status' => $status])->save();
                $password = $staff->fresh()->password;

                foreach ($actions as $action) {
                    $this->actingAs($this->registrar)->post(route($action, $staff))
                        ->assertForbidden();
                    $this->assertSame($status, $staff->fresh()->status, "{$action} on a {$status} {$staff->email}");
                    $this->assertSame($password, $staff->fresh()->password);
                }
            }
            $this->assertCount(0, AuditLog::where('entity_type', 'User')->where('entity_id', $staff->id)->where('user_id', $this->registrar->id)->get());
        }

        // The older guardian "Re-send invite" address goes through the same check.
        $guardianRecord = \App\Models\Guardian::create(['user_id' => $teacherAndParent->id, 'relationship' => 'Father', 'phone' => '0911']);
        $teacherAndParent->forceFill(['status' => 'Pending'])->save();
        $this->actingAs($this->registrar)->post(route('registrar.guardians.resend-invite', $guardianRecord))->assertForbidden();

        Notification::assertNothingSent();
    }

    // ── Kept rules ─────────────────────────────────────────────────────────

    public function test_self_deactivation_and_the_last_active_admin_rules_still_hold(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $this->admin))->assertSessionHas('warning');
        $this->assertSame('Active', $this->admin->fresh()->status);

        // Two Admins; the second is deactivated, leaving the first as the only active Admin.
        $second = $this->makeStaff('admin', 'Admin', 'admin2@test.local')->user;
        $accounts = app(\App\Services\UserAccountService::class);
        $accounts->deactivate($this->admin, $second);

        // The second Admin (acting from a session opened before) cannot deactivate the last one.
        try {
            $accounts->deactivate($second, $this->admin);
            $this->fail('Deactivating the last active Admin should be refused.');
        } catch (\App\Exceptions\AccountChangeRefused $e) {
            $this->assertStringContainsString('only active Admin', $e->getMessage());
        }
        $this->assertSame('Active', $this->admin->fresh()->status);

        // Reactivating the second Admin (who had a password) brings them back Active.
        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $second));
        $this->assertSame('Active', $second->fresh()->status);
    }

    // ── Migration fill-in ──────────────────────────────────────────────────

    public function test_the_migration_fills_password_set_at_by_the_agreed_rules(): void
    {
        $active = $this->makeAccount('teacher', 'a@test.local');
        $activeInvited = $this->makeAccount('guardian', 'b@test.local');
        $activeInvited->forceFill(['email_verified_at' => now()->subDays(3)])->save();
        $pending = $this->makeAccount('guardian', 'c@test.local', 'Pending');
        $inactiveWasActive = $this->makeAccount('teacher', 'd@test.local');
        $inactiveWasPending = $this->makeAccount('guardian', 'e@test.local', 'Pending');
        $noRole = User::create(['name' => 'HR record', 'email' => 'x.s1@internal.yasis.edu', 'password' => Hash::make('x'), 'status' => 'Inactive']);
        $erased = $this->makeAccount('teacher', 'f@test.local', 'Inactive');
        $erased->forceFill(['email' => "erased-{$erased->id}@deleted.yasis.edu"])->save();

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $inactiveWasActive));
        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $inactiveWasPending));

        DB::table('users')->update(['password_set_at' => null]);
        (require database_path('migrations/2026_11_23_090000_add_password_set_at_to_users_table.php'))->fillExistingAccounts();

        $value = fn (User $u) => DB::table('users')->where('id', $u->id)->value('password_set_at');

        $this->assertSame($active->created_at->format('Y-m-d H:i:s'), $value($active), 'Active: created_at');
        $this->assertSame($activeInvited->fresh()->email_verified_at->format('Y-m-d H:i:s'), $value($activeInvited), 'Active: email_verified_at first');
        $this->assertNull($value($pending), 'Pending stays empty');
        $this->assertNotNull($value($inactiveWasActive), 'Inactive, was Active');
        $this->assertNull($value($inactiveWasPending), 'Inactive, was Pending when deactivated');
        $this->assertNull($value($noRole), 'Inactive, no role');
        $this->assertNull($value($erased), 'Inactive, erased');
    }
}
