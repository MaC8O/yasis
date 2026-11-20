<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * A signed-in user who opens /login or / must land on their own dashboard,
 * not bounce between / and /login forever (ERR_TOO_MANY_REDIRECTS).
 */
class SignedInRedirectTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public static function staffRoles(): array
    {
        return [
            ['admin', 'Admin'], ['principal', 'Principal'], ['vp_academic', 'VP_Academic'], ['registrar', 'Registrar'],
            ['teacher', 'Teacher'], ['treasurer', 'Treasurer'], ['hr_office', 'HR_Office'],
        ];
    }

    /** @dataProvider staffRoles */
    public function test_signed_in_staff_opening_login_or_root_reach_their_dashboard(string $role, string $roleType): void
    {
        $this->seedRoles();
        $this->seedAcademicCalendar();
        $user = $this->makeStaff($role, $roleType)->user;

        $this->actingAs($user)->get('/login')->assertRedirect("/{$role}/dashboard");

        // "/" → "/login" → own dashboard, ending in a page, not a loop.
        $this->actingAs($user)->followingRedirects()->get('/')->assertOk();
        $this->actingAs($user)->get('/')->assertRedirect('/login');
    }

    public function test_signed_in_guardian_and_student_reach_their_dashboard(): void
    {
        $this->seedRoles();

        foreach (['guardian', 'student'] as $role) {
            $user = User::create(['name' => ucfirst($role), 'email' => "{$role}@test.local", 'password' => Hash::make('password'), 'status' => 'Active']);
            $user->assignRole($role);

            $this->actingAs($user)->get('/login')->assertRedirect("/{$role}/dashboard");
        }
    }

    public function test_a_signed_in_user_without_a_role_goes_to_the_calendar_not_a_loop(): void
    {
        $this->seedRoles();
        $this->seedAcademicCalendar();
        $user = User::create(['name' => 'No Role', 'email' => 'norole@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);

        $this->actingAs($user)->get('/login')->assertRedirect('/calendar');
        $this->actingAs($user)->followingRedirects()->get('/')->assertOk();
    }

    public function test_signed_out_visitors_still_get_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('name="password"', false);
    }
}
