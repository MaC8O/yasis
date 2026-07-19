<?php

namespace Tests\Feature;

use App\Services\AuditIntegrityService;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * §3.8 tamper-evidence: audit rows are HMAC-hash-chained, so any post-hoc edit or
 * deletion breaks the chain and is detectable.
 */
class AuditTamperEvidenceTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    /** @return array{0: \App\Models\StaffProfile, 1: list<int>} */
    private function seedChain(int $n = 3): array
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');
        $audit = app(AuditService::class);

        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = $audit->log($admin->user, "Action {$i}", 'System', $i)->id;
        }

        return [$admin, $ids];
    }

    public function test_a_clean_chain_verifies_ok(): void
    {
        $this->seedChain();

        $result = app(AuditIntegrityService::class)->verify();

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['checked']);
    }

    public function test_each_row_links_to_the_previous_hash(): void
    {
        [, $ids] = $this->seedChain();

        $rows = \App\Models\AuditLog::orderBy('id')->get();
        $this->assertNull($rows[0]->prev_hash);
        $this->assertSame($rows[0]->hash, $rows[1]->prev_hash);
        $this->assertSame($rows[1]->hash, $rows[2]->prev_hash);
    }

    public function test_modifying_a_row_is_detected(): void
    {
        [, $ids] = $this->seedChain();

        // Simulate a direct-DB edit that bypasses the application entirely.
        DB::table('audit_logs')->where('id', $ids[1])->update(['action' => 'Silently altered']);

        $result = app(AuditIntegrityService::class)->verify();

        $this->assertFalse($result['ok']);
        $this->assertSame($ids[1], $result['brokenAtId']);
    }

    public function test_deleting_a_row_is_detected(): void
    {
        [, $ids] = $this->seedChain();

        DB::table('audit_logs')->where('id', $ids[1])->delete();

        $result = app(AuditIntegrityService::class)->verify();

        $this->assertFalse($result['ok']);
        // The row that followed the deleted one now has a dangling prev_hash.
        $this->assertSame($ids[2], $result['brokenAtId']);
    }

    public function test_verify_endpoint_reports_intact_then_broken(): void
    {
        [$admin, $ids] = $this->seedChain();

        $this->actingAs($admin->user)->get(route('admin.audit-logs.verify'))
            ->assertRedirect()
            ->assertSessionHas('status');

        DB::table('audit_logs')->where('id', $ids[0])->update(['action' => 'tampered']);

        $this->actingAs($admin->user)->get(route('admin.audit-logs.verify'))
            ->assertSessionHasErrors('audit');
    }

    public function test_artisan_command_passes_then_fails(): void
    {
        [, $ids] = $this->seedChain();

        $this->artisan('audit:verify')->assertSuccessful();

        DB::table('audit_logs')->where('id', $ids[0])->update(['entity_id' => 999]);

        $this->artisan('audit:verify')->assertFailed();
    }
}
