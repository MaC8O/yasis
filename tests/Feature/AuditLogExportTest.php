<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §6.6 Admin Audit Logs: filterable, read-only, exportable as CSV.
 */
class AuditLogExportTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_admin_can_export_the_audit_trail_as_csv(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        AuditLog::create([
            'user_id' => $admin->user->id,
            'role' => 'admin',
            'action' => 'Created section',
            'entity_type' => 'Section',
            'entity_id' => 7,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin->user)->get('/admin/audit-logs/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('audit-logs-', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('When,User,Role,Action,Entity,"Entity ID"', $csv);
        $this->assertStringContainsString('Created section', $csv);
        $this->assertStringContainsString('Section,7', $csv);
    }

    public function test_export_respects_the_active_filters(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        AuditLog::create([
            'user_id' => $admin->user->id, 'role' => 'admin',
            'action' => 'Created section', 'entity_type' => 'Section', 'entity_id' => 1,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $admin->user->id, 'role' => 'admin',
            'action' => 'Approved leave', 'entity_type' => 'LeaveRequest', 'entity_id' => 2,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $admin->user->id, 'role' => 'admin',
            'action' => 'Old section change', 'entity_type' => 'Section', 'entity_id' => 3,
            'created_at' => now()->subMonths(2),
        ]);

        $csv = $this->actingAs($admin->user)
            ->get('/admin/audit-logs/export?search=Section&from='.now()->subWeek()->toDateString())
            ->streamedContent();

        $this->assertStringContainsString('Created section', $csv);
        $this->assertStringNotContainsString('Approved leave', $csv);
        $this->assertStringNotContainsString('Old section change', $csv);
    }

    public function test_non_admins_cannot_export_the_audit_trail(): void
    {
        $this->seedRoles();
        $principal = $this->makeStaff('principal', 'Principal');

        $this->actingAs($principal->user)->get('/admin/audit-logs/export')->assertForbidden();
    }

    public function test_audit_index_still_renders_with_filters(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        AuditLog::create([
            'user_id' => $admin->user->id, 'role' => 'admin',
            'action' => 'Created section', 'entity_type' => 'Section', 'entity_id' => 1,
            'created_at' => now(),
        ]);

        $this->actingAs($admin->user)
            ->get('/admin/audit-logs?search=section&from='.now()->subDay()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertSee('Created section')
            ->assertSee('Export CSV');
    }
}
