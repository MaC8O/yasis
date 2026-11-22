<?php

namespace Tests\Feature\Behavior;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\BehaviorCase;
use App\Models\BehaviorFlag;
use App\Models\BehaviorLedgerEntry;
use App\Models\BehaviorReport;
use App\Notifications\BehaviorNotice;
use App\Services\Behavior\BehaviorPolicySettings;
use App\Services\Behavior\BehaviorService;
use App\Services\Behavior\BehaviorStanding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/** The ledger, the cap at 0, restorations, flags, expulsion cases and the yearly reset. */
class BehaviorScoreTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    private BehaviorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        $this->service = app(BehaviorService::class);
    }

    private function deduct(int $points, string $reason = 'Serious misconduct confirmed.'): BehaviorLedgerEntry
    {
        return $this->service->createAndDeduct($this->vp, $this->student, $this->reportFields(), $points, $reason);
    }

    private function score(): int
    {
        return app(BehaviorStanding::class)->score($this->student->id, $this->year->id);
    }

    private function openStages(): array
    {
        return BehaviorFlag::open()->where('student_id', $this->student->id)->orderBy('id')->pluck('stage')->all();
    }

    public function test_a_deduction_larger_than_the_score_is_capped_at_0_and_the_ledger_shows_points_applied(): void
    {
        $this->deduct(70);
        $entry = $this->deduct(50);

        $this->assertSame(50, $entry->points_requested);
        $this->assertSame(30, $entry->points_applied);
        $this->assertSame([30, 0], [$entry->score_before, $entry->score_after]);
        $this->assertSame(0, $this->score());

        $zero = $this->deduct(5);
        $this->assertSame([5, 0, 0], [$zero->points_requested, $zero->points_applied, $zero->score_after]);

        $this->actingAs($this->vp)->get(route('behavior.students.show', $this->student))->assertSee('50 entered');
    }

    public function test_crossing_thresholds_opens_flags_and_a_restore_closes_them_but_keeps_the_history(): void
    {
        $deduction = $this->deduct(25);           // 75: Warning (≤80)
        $this->assertSame(['Warning'], $this->openStages());

        $this->actingAs($this->vp)->post(route('behavior.entries.restore', $deduction), ['points' => 10, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->vp)->post(route('behavior.entries.restore', $deduction), ['points' => 10, 'reason' => 'Part of this was a misunderstanding.'])->assertRedirect();

        $this->assertSame(85, $this->score());
        $this->assertSame([], $this->openStages());
        $flag = BehaviorFlag::where('student_id', $this->student->id)->firstOrFail();
        $this->assertNotNull($flag->closed_at);
        $this->assertSame('Points restored above the threshold', $flag->close_reason);

        $restoration = BehaviorLedgerEntry::where('type', 'Restoration')->firstOrFail();
        $this->assertSame((int) $deduction->id, (int) $restoration->restores_entry_id);
        $this->assertSame('vp_academic', AuditLog::where('action', 'Restored behavior points')->value('role'));

        $this->actingAs($this->guardianUser)->get(route('guardian.behavior.index'))->assertSee('+10 restored');
        $this->actingAs($this->vp)->get(route('behavior.students.show', $this->student))->assertSee('Closed')->assertSee('Points restored above the threshold');
    }

    public function test_a_restore_cannot_give_back_more_than_the_deduction(): void
    {
        $deduction = $this->deduct(10);
        $this->service->restore($this->vp, $deduction, 6, 'Partly a mistake on our side.');

        $this->actingAs($this->vp)->post(route('behavior.entries.restore', $deduction), ['points' => 5, 'reason' => 'Trying to restore too much'])->assertSessionHas('warning');
        $this->assertSame(96, $this->score());

        $this->actingAs($this->principal)->post(route('behavior.entries.restore', $deduction), ['points' => 1, 'reason' => 'Principal is read only'])->assertForbidden();
    }

    public function test_one_entry_crossing_several_stages_opens_every_flag_but_sends_guardians_one_notice(): void
    {
        Notification::fake();
        $this->deduct(65); // 35: Warning, Final warning and Expulsion review at once

        $this->assertSame(['Warning', 'FinalWarning', 'ExpulsionReview'], $this->openStages());
        $this->assertSame(1, BehaviorCase::where('student_id', $this->student->id)->where('status', 'Open')->count());

        // Exactly one notice (in-app + one e-mail) per guardian, naming the highest stage and the case.
        Notification::assertSentToTimes($this->guardianUser, BehaviorNotice::class, 1);
        Notification::assertSentTo($this->guardianUser, BehaviorNotice::class, function (BehaviorNotice $n, $channels) {
            return in_array('mail', $channels) && str_contains($n->body, 'Expulsion review stage') && str_contains($n->body, 'expulsion review has been opened')
                && ! str_contains($n->body, 'Warning stage');
        });
        Notification::assertSentToTimes($this->vp, BehaviorNotice::class, 1);
        Notification::assertSentToTimes($this->principal, BehaviorNotice::class, 1);
    }

    public function test_reaching_expulsion_review_opens_a_case_and_restoring_points_leaves_it_open(): void
    {
        $deduction = $this->deduct(65); // 35
        $case = BehaviorCase::firstOrFail();

        $this->service->restore($this->vp, $deduction, 20, 'Part of the incident was misreported.'); // 55

        $this->assertSame('Open', $case->fresh()->status);
        $this->assertSame(['Warning', 'FinalWarning'], $this->openStages()); // Expulsion review flag closed...
        $this->actingAs($this->vp)->get(route('behavior.cases.show', $case))->assertOk()
            ->assertSee('above the Expulsion review threshold')->assertSee('55');   // ...case still open, with a note
    }

    public function test_a_threshold_change_does_not_rewrite_history_and_applies_from_the_next_entry(): void
    {
        $this->deduct(25); // 75 → Warning flag at threshold 80
        app(BehaviorPolicySettings::class)->update($this->vp, ['Warning' => 70, 'FinalWarning' => 60, 'ExpulsionReview' => 40]);

        // Saving the policy changes nothing on its own.
        $this->assertSame(['Warning'], $this->openStages());

        // Next entry: 74 is above the new Warning threshold (70), so the flag closes.
        $this->deduct(1);
        $this->assertSame([], $this->openStages());
        $this->assertSame('Above the threshold under the current policy', BehaviorFlag::firstOrFail()->close_reason);
        $this->assertSame(80, BehaviorFlag::firstOrFail()->threshold_at_open);
    }

    public function test_a_new_school_year_starts_at_100_and_restarts_office_visits_keeping_history(): void
    {
        $this->service->createAndDeduct($this->vp, $this->student, $this->reportFields(['is_office_referral' => 1]), 30, 'Damage to property.');
        $this->assertSame(70, $this->score());

        $this->year->update(['is_active' => false]);
        $next = AcademicYear::create(['year_label' => 'Next year', 'is_active' => true]);

        $summary = app(BehaviorStanding::class)->summary($this->student);
        $this->assertSame([100, 0, null], [$summary['score'], $summary['office_visits'], $summary['stage']]);
        $this->assertSame(1, BehaviorReport::count());
        $this->assertSame(70, app(BehaviorStanding::class)->score($this->student->id, $this->year->id));
        $this->actingAs($this->guardianUser)->get(route('guardian.behavior.index'))->assertSee('Damage to property.');
        unset($next);
    }
}
