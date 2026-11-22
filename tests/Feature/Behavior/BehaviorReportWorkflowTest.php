<?php

namespace Tests\Feature\Behavior;

use App\Models\AuditLog;
use App\Models\BehaviorLedgerEntry;
use App\Models\BehaviorReport;
use App\Notifications\BehaviorNotice;
use App\Services\Behavior\BehaviorService;
use App\Services\Behavior\BehaviorStanding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/** Teacher reports and the VP's review: submit, edit, void, dismiss, deduct. */
class BehaviorReportWorkflowTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function submit(array $overrides = []): BehaviorReport
    {
        $this->actingAs($this->teacher)->post(route('behavior.reports.store', $this->student), $this->reportFields($overrides))->assertRedirect();

        return BehaviorReport::latest('id')->firstOrFail();
    }

    private function audit(string $action, string $type, int $id): ?AuditLog
    {
        return AuditLog::where('action', $action)->where('entity_type', $type)->where('entity_id', $id)->first();
    }

    public function test_teacher_submits_a_report_that_does_not_change_the_score_and_is_audited(): void
    {
        Notification::fake();
        $report = $this->submit(['is_office_referral' => 1]);

        $this->assertSame('Submitted', $report->status);
        $this->assertSame((int) $this->year->id, (int) $report->academic_year_id);
        $this->assertSame(100, app(BehaviorStanding::class)->score($this->student->id, $this->year->id));
        $this->assertSame(0, BehaviorLedgerEntry::count());
        $this->assertSame('teacher', $this->audit('Submitted behavior report', 'BehaviorReport', $report->id)->role);

        // In-app notice to the student and the guardian; no e-mail at submission.
        Notification::assertSentTo($this->studentUser, BehaviorNotice::class, fn ($n, $channels) => $channels === ['database']);
        Notification::assertSentTo($this->guardianUser, BehaviorNotice::class, fn ($n, $channels) => $channels === ['database']);
        Notification::assertNotSentTo($this->otherGuardianUser, BehaviorNotice::class);
    }

    public function test_student_and_guardian_see_a_submitted_report_as_awaiting_review(): void
    {
        $this->submit(['description' => 'Threw paper across the classroom twice.']);

        $this->actingAs($this->studentUser)->get(route('student.behavior.index'))->assertOk()
            ->assertSee('Awaiting review')->assertSee('Threw paper across the classroom twice.')->assertSee('100');
        $this->actingAs($this->guardianUser)->get(route('guardian.behavior.index'))->assertOk()
            ->assertSee('Awaiting review')->assertSee('Threw paper across the classroom twice.');
    }

    public function test_editing_a_submitted_report_keeps_the_earlier_version_and_shows_it_was_edited(): void
    {
        $report = $this->submit(['description' => 'First version of what happened.']);

        $this->actingAs($this->teacher)->put(route('behavior.reports.update', $report), $this->reportFields(['description' => 'Corrected version of what happened.']))->assertRedirect();

        $report->refresh();
        $this->assertSame(2, $report->version);
        $this->assertNotNull($report->edited_at);
        $this->assertSame('Corrected version of what happened.', $report->description);
        $this->assertSame('First version of what happened.', $report->versions()->where('version', 1)->value('description'));

        $audit = $this->audit('Edited behavior report', 'BehaviorReport', $report->id);
        $this->assertSame(['from' => 'First version of what happened.', 'to' => 'Corrected version of what happened.'], $audit->details['description']);

        $this->actingAs($this->guardianUser)->get(route('guardian.behavior.index'))
            ->assertSee('Edited on '.$report->edited_at->format('j M Y'))
            ->assertSee('First version of what happened.')
            ->assertSee('Corrected version of what happened.');
    }

    public function test_voiding_keeps_the_report_visible_as_withdrawn_with_the_reason(): void
    {
        $report = $this->submit();

        $this->actingAs($this->teacher)->post(route('behavior.reports.void', $report), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('Submitted', $report->fresh()->status);

        $this->actingAs($this->teacher)->post(route('behavior.reports.void', $report), ['reason' => 'Recorded the wrong student by mistake.'])->assertRedirect();

        $this->assertSame('Voided', $report->fresh()->status);
        $this->assertDatabaseHas('behavior_reports', ['id' => $report->id]);
        $this->assertNotNull($this->audit('Voided behavior report', 'BehaviorReport', $report->id));

        foreach ([[$this->studentUser, 'student.behavior.index'], [$this->guardianUser, 'guardian.behavior.index']] as [$user, $route]) {
            $this->actingAs($user)->get(route($route))->assertSee('Withdrawn')->assertSee('Recorded the wrong student by mistake.');
        }
    }

    public function test_teacher_cannot_edit_or_void_after_review_or_someone_elses_report(): void
    {
        $report = $this->submit();
        $otherTeacherReport = app(BehaviorService::class)->submitReport($this->teacher, $this->otherStudent, $this->reportFields());

        // Another teacher (in scope via homeroom) still cannot touch it.
        $this->section->update(['homeroom_teacher_id' => $this->otherTeacher->id]);
        $this->actingAs($this->otherTeacher)->put(route('behavior.reports.update', $otherTeacherReport), $this->reportFields(['description' => 'Rewritten by someone else']))->assertForbidden();
        $this->actingAs($this->otherTeacher)->post(route('behavior.reports.void', $otherTeacherReport), ['reason' => 'Not mine but trying'])->assertForbidden();

        app(BehaviorService::class)->dismiss($this->vp, $report, 'Spoke with the student; resolved.');
        $this->actingAs($this->teacher)->put(route('behavior.reports.update', $report), $this->reportFields(['description' => 'Too late to change this one']))->assertSessionHas('warning');
        $this->actingAs($this->teacher)->post(route('behavior.reports.void', $report), ['reason' => 'Too late to withdraw'])->assertSessionHas('warning');
        $this->assertSame('Dismissed', $report->fresh()->status);
    }

    public function test_vp_dismisses_with_a_required_reason_and_guardians_are_emailed(): void
    {
        $report = $this->submit();
        Notification::fake();

        $this->actingAs($this->vp)->post(route('behavior.reports.dismiss', $report), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->vp)->post(route('behavior.reports.dismiss', $report), ['reason' => 'Talked to the student; no deduction needed.'])->assertRedirect();

        $report->refresh();
        $this->assertSame('Dismissed', $report->status);
        $this->assertSame('Talked to the student; no deduction needed.', $report->dismiss_reason);
        $this->assertSame(100, app(BehaviorStanding::class)->score($this->student->id, $this->year->id));
        $this->assertSame('vp_academic', $this->audit('Dismissed behavior report', 'BehaviorReport', $report->id)->role);
        Notification::assertSentTo($this->guardianUser, BehaviorNotice::class, fn ($n, $channels) => in_array('mail', $channels) && in_array('database', $channels));
        Notification::assertSentTo($this->studentUser, BehaviorNotice::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_vp_deducts_on_a_report_and_the_audit_is_in_the_same_transaction(): void
    {
        $report = $this->submit();

        $this->actingAs($this->vp)->post(route('behavior.reports.deduct', $report), ['points' => 10, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->vp)->post(route('behavior.reports.deduct', $report), ['points' => 10, 'reason' => 'Repeated disruption after a warning.'])->assertRedirect();

        $entry = BehaviorLedgerEntry::firstOrFail();
        $this->assertSame([10, 10, 100, 90], [$entry->points_requested, $entry->points_applied, $entry->score_before, $entry->score_after]);
        $this->assertSame('Deducted', $report->fresh()->status);
        $this->assertSame('vp_academic', $this->audit('Deducted behavior points', 'BehaviorLedgerEntry', $entry->id)->role);
    }

    public function test_a_failed_audit_write_rolls_the_deduction_back(): void
    {
        $report = app(BehaviorService::class)->submitReport($this->teacher, $this->student, $this->reportFields());
        // Registered before the first request, so the controller is built with the failing audit service.
        $this->mock(\App\Services\AuditService::class, fn ($m) => $m->shouldReceive('log')->andThrow(new RuntimeException('audit store down')));

        $this->actingAs($this->vp)->post(route('behavior.reports.deduct', $report), ['points' => 5, 'reason' => 'Should not persist.'])->assertServerError();

        $this->assertSame('Submitted', $report->fresh()->status);
        $this->assertSame(0, BehaviorLedgerEntry::count());
        $this->assertSame(100, app(BehaviorStanding::class)->score($this->student->id, $this->year->id));
    }

    public function test_a_non_vp_trying_to_deduct_gets_403(): void
    {
        $report = $this->submit();

        foreach ([$this->principal, $this->teacher, $this->admin, $this->registrar, $this->guardianUser, $this->studentUser] as $user) {
            $this->actingAs($user)->post(route('behavior.reports.deduct', $report), ['points' => 10, 'reason' => 'Trying without authority'])->assertForbidden();
            $this->actingAs($user)->post(route('behavior.reports.vp-store', $this->student), $this->reportFields() + ['points' => 10, 'reason' => 'Trying without authority'])->assertForbidden();
        }

        // ...and the service refuses too, without any route in front of it.
        try {
            app(BehaviorService::class)->deduct($this->principal, $report, 10, 'Direct service call');
            $this->fail('A non-VP deduction should be refused.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
        }

        $this->assertSame(0, BehaviorLedgerEntry::count());
        $this->assertSame('Submitted', $report->fresh()->status);
    }

    public function test_vp_creates_a_report_and_deducts_in_one_step(): void
    {
        $this->actingAs($this->vp)->post(route('behavior.reports.vp-store', $this->student), $this->reportFields(['is_office_referral' => 1]) + ['points' => 15, 'reason' => 'Fighting in the corridor.'])
            ->assertRedirect(route('behavior.students.show', $this->student));

        $report = BehaviorReport::firstOrFail();
        $this->assertSame(['VP', 'Deducted'], [$report->source, $report->status]);
        $this->assertSame(85, app(BehaviorStanding::class)->score($this->student->id, $this->year->id));
        $this->assertNotNull($this->audit('Created behavior report (VP)', 'BehaviorReport', $report->id));
    }

    public function test_voided_reports_and_their_referrals_are_not_counted_as_office_visits(): void
    {
        $submitted = $this->submit(['is_office_referral' => 1]);
        $dismissed = $this->submit(['is_office_referral' => 1]);
        $deducted = $this->submit(['is_office_referral' => 1]);
        $voided = $this->submit(['is_office_referral' => 1]);
        $this->submit(['is_office_referral' => 0]);

        $service = app(BehaviorService::class);
        $service->dismiss($this->vp, $dismissed, 'No deduction needed here.');
        $service->deduct($this->vp, $deducted, 5, 'Disruption confirmed.');
        $service->voidReport($this->teacher, $voided, 'Duplicate of another report.');

        $this->assertSame(3, app(BehaviorStanding::class)->officeVisits($this->student->id, $this->year->id));
        $this->actingAs($this->guardianUser)->get(route('guardian.behavior.index'))->assertSeeInOrder(['Office visits this year', '3']);
        unset($submitted);
    }

    public function test_reports_are_never_deleted_there_is_no_delete_route(): void
    {
        $report = $this->submit();

        $this->actingAs($this->vp)->delete('/behavior/reports/'.$report->id)->assertStatus(405);
        $this->actingAs($this->teacher)->delete('/behavior/reports/'.$report->id)->assertStatus(405);
        $this->assertDatabaseHas('behavior_reports', ['id' => $report->id]);
    }
}
