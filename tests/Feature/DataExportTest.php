<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_admin_can_download_a_data_snapshot_zip(): void
    {
        $this->seedRoles();
        $this->seedDepartment();
        $this->seedAcademicCalendar();
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $response = $this->actingAs($admin->user)->get('/admin/export-snapshot');

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringContainsString('isms-snapshot-', $response->headers->get('content-disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->user->id,
            'action' => 'Generated data export snapshot',
        ]);
    }

    public function test_non_admin_cannot_download_the_snapshot(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $this->actingAs($teacher->user)->get('/admin/export-snapshot')->assertForbidden();
    }
}
