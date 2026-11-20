<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §3.3 role home addresses: /admin, /principal, /vp, /registrar, /teacher, /treasurer, /hr,
 * /guardian, /student must reach the role's dashboard (via sign-in when signed out), never 404.
 */
class RoleHomeRedirectTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public static function homes(): array
    {
        return [
            ['/admin', '/admin/dashboard'], ['/principal', '/principal/dashboard'], ['/vp', '/vp_academic/dashboard'],
            ['/vp_academic', '/vp_academic/dashboard'], ['/registrar', '/registrar/dashboard'], ['/teacher', '/teacher/dashboard'],
            ['/treasurer', '/treasurer/dashboard'], ['/hr', '/hr_office/dashboard'], ['/hr_office', '/hr_office/dashboard'],
            ['/guardian', '/guardian/dashboard'], ['/student', '/student/dashboard'],
        ];
    }

    /** @dataProvider homes */
    public function test_short_role_address_forwards_to_the_dashboard(string $short, string $dashboard): void
    {
        $this->get($short)->assertRedirect($dashboard);
        $this->get($dashboard)->assertRedirect('/login'); // signed out → sign-in page, not 404
    }

    public function test_admin_typing_slash_admin_signs_in_and_lands_on_the_dashboard(): void
    {
        $this->seedRoles();
        $this->seedAcademicCalendar();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@yasis.edu');

        // Signed out: /admin → /admin/dashboard → /login; signing in returns to the dashboard.
        $this->get('/admin')->assertRedirect('/admin/dashboard');
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => 'admin@yasis.edu', 'password' => 'password'])->assertRedirect('/admin/dashboard');

        // Signed in: /admin goes straight to a working dashboard.
        $this->actingAs($admin->user)->followingRedirects()->get('/admin')->assertOk()->assertSee('Admin Dashboard');
    }

    public function test_another_role_cannot_use_slash_admin_to_reach_admin_pages(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $this->actingAs($teacher->user)->get('/admin')->assertRedirect('/admin/dashboard');
        $this->actingAs($teacher->user)->get('/admin/dashboard')->assertForbidden();
    }
}
