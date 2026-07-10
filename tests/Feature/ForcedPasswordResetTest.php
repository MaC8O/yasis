<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §3.1/§5/§6.2 — admin-initiated reset forces the user through /set-password before
 * they can reach any other route.
 */
class ForcedPasswordResetTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_flagged_user_is_sent_to_set_password_on_login(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $staff->user->forceFill(['must_reset_password' => true])->save();

        $response = $this->post('/login', [
            'email' => 'teacher@test.local',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('password.set'));
        $this->assertAuthenticated();
    }

    public function test_flagged_user_cannot_reach_any_other_route(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $staff->user->forceFill(['must_reset_password' => true])->save();

        $this->actingAs($staff->user)
            ->get('/teacher/dashboard')
            ->assertRedirect(route('password.set'));
    }

    public function test_setting_a_valid_password_clears_the_flag_and_unlocks_the_portal(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $staff->user->forceFill(['must_reset_password' => true])->save();

        $response = $this->actingAs($staff->user)->post('/set-password', [
            'password' => 'freshPass1',
            'password_confirmation' => 'freshPass1',
        ]);

        $response->assertRedirect('/teacher/dashboard');

        $staff->user->refresh();
        $this->assertFalse($staff->user->must_reset_password);
        $this->assertTrue(Hash::check('freshPass1', $staff->user->password));
    }

    public function test_weak_password_is_rejected_and_the_flag_remains(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $staff->user->forceFill(['must_reset_password' => true])->save();

        $response = $this->actingAs($staff->user)->from('/set-password')->post('/set-password', [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertRedirect('/set-password');
        $response->assertSessionHasErrors('password');

        $staff->user->refresh();
        $this->assertTrue($staff->user->must_reset_password);
    }

    public function test_letters_only_password_is_rejected(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $staff->user->forceFill(['must_reset_password' => true])->save();

        $response = $this->actingAs($staff->user)->from('/set-password')->post('/set-password', [
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ]);

        $response->assertSessionHasErrors('password');
        $staff->user->refresh();
        $this->assertTrue($staff->user->must_reset_password);
    }

    public function test_admin_reset_flags_the_account_and_queues_an_email_without_exposing_a_password(): void
    {
        Notification::fake();

        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $target = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $response = $this->actingAs($admin->user)
            ->post(route('admin.users.reset-password', $target->user));

        $response->assertRedirect();

        $target->user->refresh();
        $this->assertTrue($target->user->must_reset_password);

        Notification::assertSentTo($target->user, ResetPassword::class);
    }
}
