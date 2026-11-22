<?php

namespace App\Services\Behavior;

use App\Exceptions\ActionRefused;
use App\Models\BehaviorCase;
use App\Models\BehaviorCaseDecision;
use App\Models\BehaviorCategory;
use App\Models\BehaviorFlag;
use App\Models\BehaviorLedgerEntry;
use App\Models\BehaviorReport;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Every change to behavior records. Each method runs in one transaction that writes the change
 * and its audit entry together. Anything that moves a score first locks the student's row, so two
 * deductions at the same moment queue: the second one reads the score the first one wrote.
 *
 * Lock order is always student → report/flags/case, to avoid deadlocks between writers.
 */
class BehaviorService
{
    public function __construct(
        private AuditService $audit,
        private BehaviorStanding $standing,
        private BehaviorPolicySettings $policy,
        private BehaviorAccess $access,
        private BehaviorNotifier $notify,
        private StudentExitRequestService $exits,
    ) {}

    // ---------------------------------------------------------------- teacher reports

    public function submitReport(User $teacher, Student $student, array $data): BehaviorReport
    {
        $this->access->assertTeacherCanReport($teacher, $student);
        $yearId = $this->requireActiveYear();
        $this->assertActiveCategory($data['behavior_category_id']);

        return DB::transaction(function () use ($teacher, $student, $data, $yearId) {
            $report = BehaviorReport::create([
                'student_id' => $student->id,
                'academic_year_id' => $yearId,
                'reported_by' => $teacher->id,
                'source' => 'Teacher',
                'incident_date' => $data['incident_date'],
                'behavior_category_id' => $data['behavior_category_id'],
                'description' => $data['description'],
                'is_office_referral' => (bool) ($data['is_office_referral'] ?? false),
                'status' => BehaviorReport::SUBMITTED,
                'version' => 1,
            ]);

            $this->audit->log($teacher, 'Submitted behavior report', 'BehaviorReport', $report->id, [
                'student_id' => $student->id,
                'category' => $report->category->name,
                'office_referral' => $report->is_office_referral,
            ]);

            $this->notify->family($student, 'report_submitted', 'New behavior report',
                "A teacher recorded a behavior report for {$student->name}: {$report->category->name} on {$report->incident_date->format('j M Y')}"
                .($report->is_office_referral ? ' (sent to the office)' : '')
                .'. It is awaiting review by the Vice Principal and does not change the behavior score.');

            return $report;
        });
    }

    public function editReport(User $teacher, BehaviorReport $report, array $data): BehaviorReport
    {
        return DB::transaction(function () use ($teacher, $report, $data) {
            $report = $this->lockOwnSubmittedReport($teacher, $report, 'edit');
            if ((int) $data['behavior_category_id'] !== (int) $report->behavior_category_id) {
                $this->assertActiveCategory($data['behavior_category_id']);
            }

            // Keep the version being replaced, exactly as it read.
            $report->versions()->create([
                'version' => $report->version,
                'incident_date' => $report->incident_date,
                'behavior_category_id' => $report->behavior_category_id,
                'description' => $report->description,
                'is_office_referral' => $report->is_office_referral,
                'recorded_by' => $report->reported_by,
                'recorded_at' => $report->edited_at ?? $report->created_at,
            ]);

            $report->fill([
                'incident_date' => $data['incident_date'],
                'behavior_category_id' => $data['behavior_category_id'],
                'description' => $data['description'],
                'is_office_referral' => (bool) ($data['is_office_referral'] ?? false),
            ]);
            $changes = $this->diff($report);
            if ($changes === []) {
                throw new ActionRefused('Nothing was changed, so no new version was saved.');
            }
            $report->version += 1;
            $report->edited_at = now();
            $report->save();

            $this->audit->log($teacher, 'Edited behavior report', 'BehaviorReport', $report->id, ['version' => $report->version] + $changes);

            $this->notify->family($report->student, 'report_edited', 'Behavior report updated',
                "The behavior report for {$report->student->name} from {$report->incident_date->format('j M Y')} was edited by the teacher. The earlier version is kept and can be viewed in the portal.");

            return $report;
        });
    }

    public function voidReport(User $teacher, BehaviorReport $report, string $reason): BehaviorReport
    {
        return DB::transaction(function () use ($teacher, $report, $reason) {
            $report = $this->lockOwnSubmittedReport($teacher, $report, 'withdraw');

            $report->update([
                'status' => BehaviorReport::VOIDED, 'voided_by' => $teacher->id,
                'voided_at' => now(), 'void_reason' => $reason,
            ]);

            $this->audit->log($teacher, 'Voided behavior report', 'BehaviorReport', $report->id, [
                'status' => ['from' => BehaviorReport::SUBMITTED, 'to' => BehaviorReport::VOIDED], 'reason' => $reason,
            ]);

            $this->notify->family($report->student, 'report_voided', 'Behavior report withdrawn',
                "The teacher withdrew the behavior report for {$report->student->name} from {$report->incident_date->format('j M Y')}. Reason: {$reason}");

            return $report;
        });
    }

    // ---------------------------------------------------------------- VP review

    public function dismiss(User $vp, BehaviorReport $report, string $reason): BehaviorReport
    {
        $this->assertVp($vp);

        return DB::transaction(function () use ($vp, $report, $reason) {
            $report = $this->lockSubmittedForReview($report);

            $report->update([
                'status' => BehaviorReport::DISMISSED, 'reviewed_by' => $vp->id,
                'reviewed_at' => now(), 'dismiss_reason' => $reason,
            ]);

            $this->audit->log($vp, 'Dismissed behavior report', 'BehaviorReport', $report->id, [
                'status' => ['from' => BehaviorReport::SUBMITTED, 'to' => BehaviorReport::DISMISSED], 'reason' => $reason,
            ]);

            $this->notify->family($report->student, 'report_dismissed', 'Behavior report reviewed — no deduction',
                "The Vice Principal reviewed the behavior report for {$report->student->name} from {$report->incident_date->format('j M Y')} and made no deduction. Reason: {$reason}",
                emailGuardians: true);

            return $report;
        });
    }

    public function deduct(User $vp, BehaviorReport $report, int $points, string $reason): BehaviorLedgerEntry
    {
        $this->assertVp($vp);

        return DB::transaction(function () use ($vp, $report, $points, $reason) {
            $student = $this->lockStudent($report->student_id);
            $report = $this->lockSubmittedForReview($report);
            if ((int) $report->academic_year_id !== $this->requireActiveYear()) {
                throw new ActionRefused('This report is from an earlier school year, and deductions only apply to the current year\'s score. Dismiss it with a reason, or record a new report for this year.');
            }

            $report->update(['status' => BehaviorReport::DEDUCTED, 'reviewed_by' => $vp->id, 'reviewed_at' => now()]);

            return $this->deductLocked($vp, $student, $report, $points, $reason);
        });
    }

    /** The VP records an incident and deducts in one step. */
    public function createAndDeduct(User $vp, Student $student, array $data, int $points, string $reason): BehaviorLedgerEntry
    {
        $this->assertVp($vp);
        $yearId = $this->requireActiveYear();
        $this->assertActiveCategory($data['behavior_category_id']);

        return DB::transaction(function () use ($vp, $student, $data, $points, $reason, $yearId) {
            $student = $this->lockStudent($student->id);

            $report = BehaviorReport::create([
                'student_id' => $student->id,
                'academic_year_id' => $yearId,
                'reported_by' => $vp->id,
                'source' => 'VP',
                'incident_date' => $data['incident_date'],
                'behavior_category_id' => $data['behavior_category_id'],
                'description' => $data['description'],
                'is_office_referral' => (bool) ($data['is_office_referral'] ?? false),
                'status' => BehaviorReport::DEDUCTED,
                'reviewed_by' => $vp->id,
                'reviewed_at' => now(),
                'version' => 1,
            ]);

            $this->audit->log($vp, 'Created behavior report (VP)', 'BehaviorReport', $report->id, [
                'student_id' => $student->id, 'category' => $report->category->name, 'office_referral' => $report->is_office_referral,
            ]);

            return $this->deductLocked($vp, $student, $report, $points, $reason);
        });
    }

    /** Give back points of one deduction (a mistake). At most what is left of that deduction. */
    public function restore(User $vp, BehaviorLedgerEntry $deduction, int $points, string $reason): BehaviorLedgerEntry
    {
        $this->assertVp($vp);

        return DB::transaction(function () use ($vp, $deduction, $points, $reason) {
            $student = $this->lockStudent($deduction->student_id);
            $deduction = BehaviorLedgerEntry::whereKey($deduction->id)->firstOrFail();

            if (! $deduction->isDeduction()) {
                // The page only offers Restore on deductions, and an entry's type never changes.
                throw ActionRefused::unexpected('behavior.restore_non_deduction', 'Only a deduction can be restored.');
            }
            if ((int) $deduction->academic_year_id !== $this->requireActiveYear()) {
                throw new ActionRefused('That deduction belongs to an earlier school year. Earlier years are kept as history and cannot be changed.');
            }
            $remaining = $deduction->restorablePoints();
            if ($points > $remaining) {
                throw new ActionRefused($remaining === 0
                    ? 'All points of that deduction have already been restored.'
                    : "Only {$remaining} point(s) of that deduction are left to restore.");
            }

            [$entry, $changes] = $this->applyEntry($vp, $student, BehaviorLedgerEntry::RESTORATION, $points, $reason, null, $deduction->id);

            $this->audit->log($vp, 'Restored behavior points', 'BehaviorLedgerEntry', $entry->id, [
                'student_id' => $student->id, 'restores_entry_id' => $deduction->id, 'points' => $entry->points_applied,
                'score' => ['from' => $entry->score_before, 'to' => $entry->score_after], 'reason' => $reason,
            ]);

            $closedNote = collect($changes['closed'])->isNotEmpty()
                ? ' '.$student->name.' is no longer at the '.collect($changes['closed'])->map(fn ($f) => BehaviorFlag::label($f->stage))->implode(' / ').' stage.'
                : '';
            $this->notify->family($student, 'points_restored', 'Behavior points restored',
                "{$entry->points_applied} point(s) were restored to {$student->name}'s behavior score. Reason: {$reason}. Score now {$entry->score_after}/100.{$closedNote}",
                emailGuardians: true);

            return $entry;
        });
    }

    // ---------------------------------------------------------------- expulsion review

    public function decideCase(User $actor, BehaviorCase $case, string $decision, string $reason): BehaviorCase
    {
        $role = match (true) {
            $actor->hasRole('vp_academic') => 'VP',
            $actor->hasRole('principal') => 'Principal',
            default => throw new AuthorizationException('Only the VP and the Principal decide expulsion review cases.'),
        };

        return DB::transaction(function () use ($actor, $case, $decision, $reason, $role) {
            $case = BehaviorCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if (! $case->isOpen()) {
                throw new ActionRefused('This case has already been decided.');
            }
            if ($case->awaiting !== $role) {
                throw new ActionRefused($role === 'Principal'
                    ? 'The VP decides first in each round. The Principal can decide once the VP has.'
                    : 'You have already decided this round. It is waiting for the Principal.');
            }

            BehaviorCaseDecision::create([
                'behavior_case_id' => $case->id, 'round' => $case->round, 'role' => $role,
                'decision' => $decision, 'reason' => $reason, 'decided_by' => $actor->id, 'decided_at' => now(),
            ]);
            $this->audit->log($actor, "Recorded {$role} decision on expulsion review", 'BehaviorCase', $case->id, [
                'round' => $case->round, 'decision' => $decision, 'reason' => $reason,
            ]);

            $student = $case->student;

            if ($role === 'VP') {
                $case->update(['awaiting' => 'Principal']);
                $this->notify->staff(['principal'], $student, 'case_awaiting_principal', 'Expulsion review: your decision is needed',
                    "The VP recorded a decision ({$decision}) for {$student->name}'s expulsion review, round {$case->round}. The case needs your decision.",
                    route('behavior.cases.show', $case));

                return $case;
            }

            $vpDecision = $case->decisions()->where('round', $case->round)->where('role', 'VP')->value('decision');

            if ($vpDecision === $decision) {
                return $this->finalizeCase($actor, $case, $decision, 'Agreed');
            }
            if ($case->round >= BehaviorCase::MAX_ROUNDS) {
                return $this->finalizeCase($actor, $case, 'Monitor', 'DefaultAfterRounds');
            }

            $case->update(['round' => $case->round + 1, 'awaiting' => 'VP']);
            $this->notify->staff(['vp_academic'], $student, 'case_disagreement', 'Expulsion review: decisions differ',
                "For {$student->name}, the VP chose {$vpDecision} and the Principal chose {$decision}. Round {$case->round} of ".BehaviorCase::MAX_ROUNDS.' is waiting for the VP.',
                route('behavior.cases.show', $case));

            return $case;
        });
    }

    private function finalizeCase(User $actor, BehaviorCase $case, string $outcome, string $resolution): BehaviorCase
    {
        $case->update(['status' => 'Decided', 'outcome' => $outcome, 'resolution' => $resolution, 'awaiting' => null, 'decided_at' => now()]);

        $this->audit->log($actor, 'Decided expulsion review case', 'BehaviorCase', $case->id, [
            'outcome' => $outcome, 'resolution' => $resolution, 'rounds' => $case->round,
        ]);

        $student = $case->student;
        if ($outcome === 'Expel') {
            $reasons = $case->decisions()->where('round', $case->round)->get()
                ->map(fn ($d) => "{$d->role}: {$d->reason}")->implode(' | ');
            $this->exits->createForCase($actor, $case, "Expulsion decided by the VP and the Principal. {$reasons}");
        }

        $text = match (true) {
            $resolution === 'DefaultAfterRounds' => "The VP and the Principal did not reach the same decision after ".BehaviorCase::MAX_ROUNDS.' rounds, so the case was closed as Monitor: '.$student->name.' stays enrolled and their behavior will be monitored.',
            $outcome === 'Expel' => "The VP and the Principal decided on expulsion for {$student->name}. The Registrar will contact you about the next steps.",
            $outcome === 'Monitor' => "The VP and the Principal decided that {$student->name} stays enrolled and their behavior will be monitored.",
            default => "The VP and the Principal closed the expulsion review for {$student->name} with no further action.",
        };

        $this->notify->family($student, 'case_decided', 'Expulsion review decided', $text, emailGuardians: true, includeStudent: false);
        $this->notify->staff(['vp_academic', 'principal'], $student, 'case_decided', 'Expulsion review decided', $text, route('behavior.cases.show', $case));

        return $case;
    }

    // ---------------------------------------------------------------- ledger core

    private function deductLocked(User $vp, Student $student, BehaviorReport $report, int $points, string $reason): BehaviorLedgerEntry
    {
        [$entry, $changes] = $this->applyEntry($vp, $student, BehaviorLedgerEntry::DEDUCTION, $points, $reason, $report->id);

        $this->audit->log($vp, 'Deducted behavior points', 'BehaviorLedgerEntry', $entry->id, [
            'student_id' => $student->id, 'behavior_report_id' => $report->id,
            'points_requested' => $entry->points_requested, 'points_applied' => $entry->points_applied,
            'score' => ['from' => $entry->score_before, 'to' => $entry->score_after], 'reason' => $reason,
        ]);

        $capped = $entry->points_applied < $entry->points_requested
            ? " ({$entry->points_requested} were entered; the score cannot go below 0)" : '';
        $body = "{$entry->points_applied} point(s) were deducted from {$student->name}'s behavior score{$capped} for "
            ."{$report->category->name} on {$report->incident_date->format('j M Y')}. Reason: {$reason}. Score now {$entry->score_after}/100.";

        // One notice per entry: the deduction, the highest stage reached, and an opened case, together.
        $highest = BehaviorStanding::highestStage(collect($changes['opened'])->pluck('stage'));
        if ($highest) {
            $body .= ' '.$student->name.' has reached the '.BehaviorFlag::label($highest).' stage.';
        }
        if ($changes['case']) {
            $body .= ' An expulsion review has been opened. Nothing has been decided: the Vice Principal and the Principal will review it and you will be informed.';
        }

        $this->notify->family($student, 'points_deducted', 'Behavior points deducted', $body, emailGuardians: true);

        if ($highest) {
            $staffBody = "{$student->name} reached the ".BehaviorFlag::label($highest)." stage (score {$entry->score_after})."
                .($changes['case'] ? ' An expulsion review case has been opened and needs the VP\'s decision first.' : '');
            $this->notify->staff(['vp_academic', 'principal'], $student, 'stage_reached', 'Behavior stage reached: '.BehaviorFlag::label($highest), $staffBody,
                $changes['case'] ? route('behavior.cases.show', $changes['case']) : null);
        }

        return $entry;
    }

    /**
     * Write one ledger entry for a locked student and re-check the stages against the CURRENT
     * thresholds: open a flag for each stage now reached, close (but keep) flags for stages no
     * longer reached, and open an expulsion review case when that stage is newly reached and no
     * case is open. An open case is never closed here: restoring points leaves it open.
     *
     * @return array{0: BehaviorLedgerEntry, 1: array{opened: BehaviorFlag[], closed: BehaviorFlag[], case: ?BehaviorCase}}
     */
    private function applyEntry(User $actor, Student $student, string $type, int $points, string $reason, ?int $reportId = null, ?int $restoresId = null): array
    {
        $yearId = $this->requireActiveYear();
        $before = $this->standing->score($student->id, $yearId);
        $applied = $type === BehaviorLedgerEntry::DEDUCTION ? min($points, $before) : min($points, BehaviorStanding::START_SCORE - $before);
        $after = $type === BehaviorLedgerEntry::DEDUCTION ? $before - $applied : $before + $applied;

        $entry = BehaviorLedgerEntry::create([
            'student_id' => $student->id, 'academic_year_id' => $yearId, 'type' => $type,
            'points_requested' => $points, 'points_applied' => $applied, 'reason' => $reason,
            'behavior_report_id' => $reportId, 'restores_entry_id' => $restoresId,
            'score_before' => $before, 'score_after' => $after, 'actor_id' => $actor->id,
        ]);

        $thresholds = $this->policy->thresholds();
        $open = BehaviorFlag::open()->where('student_id', $student->id)->where('academic_year_id', $yearId)
            ->lockForUpdate()->get()->keyBy('stage');
        $changes = ['opened' => [], 'closed' => [], 'case' => null];

        foreach (BehaviorFlag::STAGES as $stage) {
            $atStage = $after <= $thresholds[$stage];

            if ($atStage && ! $open->has($stage)) {
                $flag = BehaviorFlag::create([
                    'student_id' => $student->id, 'academic_year_id' => $yearId, 'stage' => $stage,
                    'threshold_at_open' => $thresholds[$stage], 'score_at_open' => $after,
                    'opened_by_entry_id' => $entry->id, 'opened_at' => now(),
                ]);
                $this->audit->log($actor, 'Opened behavior flag: '.BehaviorFlag::label($stage), 'BehaviorFlag', $flag->id, [
                    'student_id' => $student->id, 'threshold' => $thresholds[$stage], 'score' => $after,
                ]);
                $changes['opened'][] = $flag;
            } elseif (! $atStage && $open->has($stage)) {
                $flag = $open[$stage];
                $flag->update([
                    'closed_at' => now(), 'closed_by_entry_id' => $entry->id,
                    'close_reason' => $type === BehaviorLedgerEntry::RESTORATION
                        ? 'Points restored above the threshold' : 'Above the threshold under the current policy',
                ]);
                $this->audit->log($actor, 'Closed behavior flag: '.BehaviorFlag::label($stage), 'BehaviorFlag', $flag->id, [
                    'student_id' => $student->id, 'threshold' => $thresholds[$stage], 'score' => $after,
                ]);
                $changes['closed'][] = $flag;
            }
        }

        $expulsionFlag = collect($changes['opened'])->firstWhere('stage', 'ExpulsionReview');
        if ($expulsionFlag && ! BehaviorCase::where('student_id', $student->id)->where('status', 'Open')->lockForUpdate()->exists()) {
            $case = BehaviorCase::create([
                'student_id' => $student->id, 'academic_year_id' => $yearId, 'opened_by_flag_id' => $expulsionFlag->id,
                'status' => 'Open', 'round' => 1, 'awaiting' => 'VP',
            ]);
            $this->audit->log($actor, 'Opened expulsion review case', 'BehaviorCase', $case->id, ['student_id' => $student->id, 'score' => $after]);
            $changes['case'] = $case;
        }

        return [$entry, $changes];
    }

    // ---------------------------------------------------------------- helpers

    private function lockStudent(int $studentId): Student
    {
        return Student::whereKey($studentId)->lockForUpdate()->firstOrFail();
    }

    private function lockSubmittedForReview(BehaviorReport $report): BehaviorReport
    {
        $report = BehaviorReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
        if (! $report->isSubmitted()) {
            throw new ActionRefused("This report has already been reviewed or withdrawn (status: {$report->statusLabel()}).");
        }

        return $report;
    }

    private function lockOwnSubmittedReport(User $teacher, BehaviorReport $report, string $verb): BehaviorReport
    {
        $report = BehaviorReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
        if ((int) $report->reported_by !== (int) $teacher->id || $report->source !== 'Teacher') {
            throw new AuthorizationException("You can only {$verb} reports you wrote yourself.");
        }
        if (! $report->isSubmitted()) {
            throw new ActionRefused("This report can no longer be changed: the Vice Principal has already reviewed it, or it was withdrawn (status: {$report->statusLabel()}).");
        }

        return $report;
    }

    private function assertVp(User $user): void
    {
        if (! $user->hasRole('vp_academic')) {
            throw new AuthorizationException('Only the Vice Principal can review reports and change behavior scores.');
        }
    }

    private function requireActiveYear(): int
    {
        return $this->standing->activeYearId()
            ?? throw new ActionRefused('There is no active school year. Ask the Admin to activate one first.');
    }

    private function assertActiveCategory(int|string $categoryId): void
    {
        if (! BehaviorCategory::whereKey($categoryId)->where('is_active', true)->exists()) {
            throw new ActionRefused('That category is no longer in use. Choose another one.');
        }
    }

    private function diff(BehaviorReport $report): array
    {
        $out = [];
        foreach ($report->getDirty() as $field => $to) {
            $from = $report->getOriginal($field);
            $out[$field] = [
                'from' => $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from,
                'to' => $to instanceof \DateTimeInterface ? $to->format('Y-m-d') : $to,
            ];
        }
        // Dates compare as strings; drop a "change" that is the same day.
        if (isset($out['incident_date']) && substr((string) $out['incident_date']['from'], 0, 10) === substr((string) $out['incident_date']['to'], 0, 10)) {
            unset($out['incident_date']);
        }
        if (isset($out['is_office_referral']) && (bool) $out['is_office_referral']['from'] === (bool) $out['is_office_referral']['to']) {
            unset($out['is_office_referral']);
        }

        return $out;
    }
}
