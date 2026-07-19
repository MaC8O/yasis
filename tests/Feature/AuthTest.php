<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_login_succeeds_and_redirects_to_the_users_role_dashboard(): void
    {
        $this->seedRoles();
        $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');

        $response = $this->post('/login', [
            'email' => 'registrar@test.local',
            'password' => 'password',
        ]);

        $response->assertRedirect('/registrar/dashboard');
        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->seedRoles();
        $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $response = $this->from('/login')->post('/login', [
            'email' => 'teacher@test.local',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_account_is_blocked_even_with_correct_password(): void
    {
        $this->seedRoles();
        $user = User::create([
            'name' => 'Suspended Staff',
            'email' => 'suspended@test.local',
            'password' => Hash::make('password'),
            'status' => 'Inactive',
        ]);
        $user->assignRole('teacher');

        $response = $this->from('/login')->post('/login', [
            'email' => 'suspended@test.local',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $this->actingAs($staff->user)->post('/logout');

        $this->assertGuest();
    }

    public function test_account_locks_after_five_failed_attempts_and_blocks_the_correct_password(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => 'teacher@test.local',
                'password' => 'wrong-password',
            ]);
        }

        $staff->user->refresh();
        $this->assertTrue($staff->user->isLocked());

        $response = $this->from('/login')->post('/login', [
            'email' => 'teacher@test.local',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_ip_throttled_against_password_spraying(): void
    {
        $this->seedRoles();
        $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        SystemSetting::set('login_throttle_ip_per_min', '3');

        // Spray: one failed attempt each across several different accounts — no single
        // account reaches its own lockout threshold, but the IP accumulates failures.
        for ($i = 0; $i < 3; $i++) {
            $this->from('/login')->post('/login', [
                'email' => "victim{$i}@test.local",
                'password' => 'wrong-password',
            ]);
        }

        // Even a correct credential from this IP is now refused by the per-IP gate.
        $response = $this->from('/login')->post('/login', [
            'email' => 'teacher@test.local',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Too many failed sign-in attempts from this network',
            session('errors')->first('email')
        );
    }

    public function test_successful_login_resets_the_failed_attempt_counter_and_records_last_login(): void
    {
        $this->seedRoles();
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $this->from('/login')->post('/login', ['email' => 'teacher@test.local', 'password' => 'wrong-password']);
        $this->from('/login')->post('/login', ['email' => 'teacher@test.local', 'password' => 'wrong-password']);

        $response = $this->post('/login', ['email' => 'teacher@test.local', 'password' => 'password']);
        $response->assertRedirect('/teacher/dashboard');

        $staff->user->refresh();
        $this->assertSame(0, $staff->user->failed_login_attempts);
        $this->assertNull($staff->user->locked_until);
        $this->assertNotNull($staff->user->last_login_at);
    }

    public function test_admin_can_unlock_a_locked_account(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $staff = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $staff->user->forceFill(['failed_login_attempts' => 0, 'locked_until' => now()->addMinutes(15)])->save();

        $response = $this->actingAs($admin->user)->post(route('admin.users.unlock', $staff->user));
        $response->assertRedirect();

        $staff->user->refresh();
        $this->assertFalse($staff->user->isLocked());

        $this->post('/logout');

        $loginResponse = $this->post('/login', ['email' => 'teacher@test.local', 'password' => 'password']);
        $loginResponse->assertRedirect('/teacher/dashboard');
    }
}
