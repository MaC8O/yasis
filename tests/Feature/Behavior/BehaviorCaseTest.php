<?php

namespace Tests\Feature\Behavior;

use App\Models\AuditLog;
use App\Models\BehaviorCase;
use App\Models\StudentExitRequest;
use App\Notifications\BehaviorNotice;
use App\Services\Behavior\BehaviorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/** Expulsion review: two keys (VP then Principal), three rounds, exit requests. */
class BehaviorCaseTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    private BehaviorCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
        app(BehaviorService::class)->createAndDeduct($this->vp, $this->student, $this->reportFields(), 65, 'Fighting that injured another student.');
        $this->case = BehaviorCase::firstOrFail();
    }

    private function decide($user, string $decision, string $reason = 'Considered the full record and the circumstances.')
    {
        return $this->actingAs($user)->post(route('behavior.cases.decide', $this->case), ['decision' => $decision, 'reason' => $reason]);
    }

    public function test_the_vp_decides_first_then_the_principal_each_with_a_reason(): void
    {
        $this->decide($this->principal, 'Monitor')->assertSessionHas('warning'); // out of turn
        $this->decide($this->vp, 'Monitor', '')->assertSessionHasErrors('reason');
        $this->decide($this->vp, 'Monitor')->assertSessionHas('status');
        $this->assertSame('Principal', $this->case->fresh()->awaiting);

        $this->decide($this->vp, 'Close')->assertSessionHas('warning'); // already decided this round

        $this->decide($this->principal, 'Monitor')->assertSessionHas('status');
        $case = $this->case->fresh();
        $this->assertSame(['Decided', 'Monitor', 'Agreed'], [$case->status, $case->outcome, $case->resolution]);
        $this->assertSame(2, $case->decisions()->count());
        $this->assertSame(['vp_academic', 'principal'], AuditLog::where('action', 'like', 'Recorded % decision on expulsion review')->orderBy('id')->pluck('role')->all());
    }

    public function test_only_the_vp_and_principal_can_decide(): void
    {
        foreach ([$this->teacher, $this->admin, $this->registrar, $this->guardianUser] as $user) {
            $this->decide($user, 'Close')->assertForbidden();
        }
        $this->assertSame(0, $this->case->decisions()->count());
    }

    public function test_an_expel_decision_creates_a_pending_exit_request_and_leaves_the_student_status_unchanged(): void
    {
        Notification::fake();
        $this->decide($this->vp, 'Expel');
        $this->decide($this->principal, 'Expel');

        $this->assertSame('Expel', $this->case->fresh()->outcome);
        $this->assertSame('Enrolled', $this->student->fresh()->enrollment_status);
        $request = StudentExitRequest::firstOrFail();
        $this->assertSame(['Pending', (int) $this->student->id, 'BehaviorCase', (int) $this->case->id],
            [$request->status, (int) $request->student_id, $request->source_type, (int) $request->source_id]);
        Notification::assertSentTo($this->registrar, BehaviorNotice::class);
        Notification::assertSentTo($this->guardianUser, BehaviorNotice::class, fn ($n, $c) => in_array('mail', $c) && $n->kind === 'case_decided');

        $this->actingAs($this->registrar)->get(route('registrar.students.show', $this->student))->assertSee('Pending exit request');
    }

    public function test_after_three_rounds_without_agreement_the_case_closes_as_monitor(): void
    {
        Notification::fake();
        foreach ([1, 2, 3] as $round) {
            $this->assertSame($round, $this->case->fresh()->round);
            $this->decide($this->vp, 'Expel');
            $this->decide($this->principal, 'Close');
        }

        $case = $this->case->fresh();
        $this->assertSame(['Decided', 'Monitor', 'DefaultAfterRounds'], [$case->status, $case->outcome, $case->resolution]);
        $this->assertSame(6, $case->decisions()->count());
        $this->assertSame(0, StudentExitRequest::count());
        Notification::assertSentTo($this->guardianUser, BehaviorNotice::class, fn ($n) => $n->kind === 'case_decided' && str_contains($n->body, 'closed as Monitor'));

        $this->actingAs($this->principal)->get(route('behavior.cases.show', $case))->assertSee('Round 3')->assertSee('closed as Monitor');
    }

    public function test_principal_withdraws_a_pending_exit_request_with_a_reason(): void
    {
        $this->decide($this->vp, 'Expel');
        $this->decide($this->principal, 'Expel');
        $request = StudentExitRequest::firstOrFail();
        Notification::fake();

        $this->actingAs($this->vp)->post(route('behavior.exit-requests.withdraw', $request), ['reason' => 'The VP may not withdraw this'])->assertForbidden();
        $this->actingAs($this->principal)->post(route('behavior.exit-requests.withdraw', $request), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->principal)->post(route('behavior.exit-requests.withdraw', $request), ['reason' => 'Family moved; the board asked us to reconsider.'])->assertSessionHas('status');

        $request->refresh();
        $this->assertSame(['Withdrawn', 'Family moved; the board asked us to reconsider.'], [$request->status, $request->withdraw_reason]);
        $this->assertSame('principal', AuditLog::where('action', 'Withdrew exit request')->value('role'));
        Notification::assertSentTo($this->vp, BehaviorNotice::class, fn ($n) => $n->kind === 'exit_request_withdrawn');
        Notification::assertSentTo($this->registrar, BehaviorNotice::class, fn ($n) => $n->kind === 'exit_request_withdrawn');

        // Once withdrawn it cannot be processed.
        $this->actingAs($this->registrar)->post(route('registrar.students.transfer', $this->student), ['exit_request_id' => $request->id])->assertSessionHas('warning');
        $this->assertSame('Enrolled', $this->student->fresh()->enrollment_status);
    }

    public function test_a_transfer_is_linked_to_the_exit_request_only_when_the_registrar_confirms_it(): void
    {
        $this->decide($this->vp, 'Expel');
        $this->decide($this->principal, 'Expel');
        $request = StudentExitRequest::firstOrFail();

        // Transfer without ticking the request: the student exits, the request stays pending.
        $this->actingAs($this->registrar)->post(route('registrar.students.transfer', $this->student))->assertRedirect();
        $this->assertSame('Transferred', $this->student->fresh()->enrollment_status);
        $this->assertSame('Pending', $request->fresh()->status);

        // Confirmed on the form: linked and processed.
        $this->student->update(['enrollment_status' => 'Enrolled']);
        $this->actingAs($this->registrar)->post(route('registrar.students.transfer', $this->student), ['exit_request_id' => $request->id])->assertRedirect();
        $request->refresh();
        $this->assertSame(['Processed', (int) $this->registrar->id], [$request->status, (int) $request->processed_by]);
        $this->assertSame('registrar', AuditLog::where('action', 'Processed exit request through Transfer/Drop')->value('role'));
    }

    public function test_principal_is_read_only_except_for_the_case_decision(): void
    {
        $report = $this->student->behaviorReports()->first();

        $this->actingAs($this->principal)->get(route('behavior.students.show', $this->student))->assertOk()->assertDontSee('Record and deduct');
        $this->actingAs($this->principal)->post(route('behavior.reports.dismiss', $report), ['reason' => 'Principal cannot dismiss'])->assertForbidden();
        $this->actingAs($this->principal)->post(route('behavior.categories.store'), ['name' => 'Nope'])->assertForbidden();
        $this->decide($this->vp, 'Monitor');
        $this->decide($this->principal, 'Monitor')->assertSessionHas('status');
    }
}
