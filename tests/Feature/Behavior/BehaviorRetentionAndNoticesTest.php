<?php

namespace Tests\Feature\Behavior;

use App\Models\BehaviorLedgerEntry;
use App\Models\BehaviorReport;
use App\Services\Behavior\BehaviorService;
use App\Services\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class BehaviorRetentionAndNoticesTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    public function test_retention_erasure_blanks_behavior_text_and_notices_but_keeps_the_numbers(): void
    {
        $service = app(BehaviorService::class);
        $report = $service->submitReport($this->teacher, $this->student, $this->reportFields(['description' => 'Named classmate Ko Min was pushed.', 'is_office_referral' => 1]));
        $service->deduct($this->vp, $report, 20, 'Pushed Ko Min in the corridor.');

        app(RetentionService::class)->eraseStudent($this->student, 'Family erasure request', $this->admin);

        $report->refresh();
        $entry = BehaviorLedgerEntry::firstOrFail();
        $this->assertStringContainsString('erased', $report->description);
        $this->assertStringContainsString('erased', $entry->reason);
        $this->assertSame([20, 80, 'Deducted', true], [$entry->points_applied, $entry->score_after, $report->status, $report->is_office_referral]);

        $this->assertSame(0, \DB::table('notifications')->where('data', 'like', '%Ko Min%')->count());
        $this->assertGreaterThan(0, \DB::table('notifications')->count());
    }

    public function test_family_gets_in_app_notices_they_can_open_and_mark_read(): void
    {
        app(BehaviorService::class)->submitReport($this->teacher, $this->student, $this->reportFields());

        $this->assertSame(1, $this->guardianUser->unreadNotifications()->count());
        $notice = $this->guardianUser->notifications()->first();

        $this->actingAs($this->guardianUser)->get(route('guardian.notices.index'))->assertOk()->assertSee('For you')->assertSee('New behavior report');
        $this->actingAs($this->guardianUser)->get(route('notifications.index'))->assertOk()->assertSee('awaiting review');
        $this->actingAs($this->guardianUser)->get(route('notifications.open', $notice->id))
            ->assertRedirect(route('guardian.behavior.index', ['child' => $this->student->id]));
        $this->assertSame(0, $this->guardianUser->unreadNotifications()->count());

        // Someone else's notice is not theirs to open.
        $this->actingAs($this->otherGuardianUser)->get(route('notifications.open', $notice->id))->assertNotFound();
        $this->assertSame(1, BehaviorReport::count());
    }
}
