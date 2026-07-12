<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_teacher_cannot_access_admin_routes(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $response = $this->actingAs($teacher->user)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_guardian_cannot_access_registrar_routes(): void
    {
        $this->seedRoles();
        $guardianUser = \App\Models\User::create([
            'name' => 'Guardian Test', 'email' => 'guardian@test.local',
            'password' => Hash::make('password'), 'status' => 'Active',
        ]);
        $guardianUser->assignRole('guardian');

        $response = $this->actingAs($guardianUser)->get('/registrar/students');

        $response->assertForbidden();
    }

    public function test_hr_cannot_access_treasurer_routes(): void
    {
        $this->seedRoles();
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');

        $response = $this->actingAs($hr->user)->get('/treasurer/dashboard');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_protected_routes(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $response = $this->actingAs($admin->user)->get('/admin/dashboard');

        $response->assertOk();
    }
}
