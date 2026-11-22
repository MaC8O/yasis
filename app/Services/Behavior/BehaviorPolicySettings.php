<?php

namespace App\Services\Behavior;

use App\Models\BehaviorFlag;
use App\Models\BehaviorLedgerEntry;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * The three warning thresholds. The only place they are read or written: values live in
 * system_settings (seeded by migration), never in code. Writes go through update(), which is
 * reachable only with the behavior.policy.manage permission.
 */
class BehaviorPolicySettings
{
    /** Stage => system_settings key. */
    public const KEYS = [
        'Warning' => 'behavior_threshold_warning',
        'FinalWarning' => 'behavior_threshold_final_warning',
        'ExpulsionReview' => 'behavior_threshold_expulsion_review',
    ];

    /** Form field => stage. */
    public const FIELDS = [
        'warning' => 'Warning',
        'final_warning' => 'FinalWarning',
        'expulsion_review' => 'ExpulsionReview',
    ];

    public function __construct(private AuditService $audit, private BehaviorStanding $standing) {}

    /**
     * Current thresholds, keyed by stage. A missing row is a deployment error (the data migration
     * creates them), so it fails loudly rather than guessing a number.
     *
     * @return array<string, int>
     */
    public function thresholds(): array
    {
        $rows = SystemSetting::whereIn('key', self::KEYS)->pluck('value', 'key');

        $thresholds = [];
        foreach (self::KEYS as $stage => $key) {
            if (! isset($rows[$key]) || ! ctype_digit((string) $rows[$key])) {
                throw new \RuntimeException("Behavior threshold setting '{$key}' is missing. Run the migrations.");
            }
            $thresholds[$stage] = (int) $rows[$key];
        }

        return $thresholds;
    }

    /** @param array<string, int> $new keyed by stage */
    public function update(User $actor, array $new): void
    {
        DB::transaction(function () use ($actor, $new) {
            // Lock the three rows so two saves cannot interleave and audit the wrong "old" values.
            SystemSetting::whereIn('key', self::KEYS)->lockForUpdate()->get();
            $old = $this->thresholds();

            foreach (self::KEYS as $stage => $key) {
                SystemSetting::set($key, (string) $new[$stage]);
            }

            $this->audit->log($actor, 'Updated behavior thresholds', 'BehaviorPolicy', null, [
                'old' => $this->named($old),
                'new' => $this->named($new),
            ]);
        });
    }

    /**
     * How many students would be newly flagged or unflagged at each stage under $new, compared
     * with the flags open now. Nothing changes until each student's next ledger entry.
     *
     * @param  array<string, int>  $new  keyed by stage
     * @return array<string, array{label: string, current: int, proposed: int, open: int, newly_flagged: int, unflagged: int}>
     */
    public function preview(array $new): array
    {
        $yearId = $this->standing->activeYearId();
        $current = $this->thresholds();
        $result = [];

        // Students with no entry this year are at 100, above every allowed threshold (max 99).
        $scores = $yearId ? $this->standing->scoresForYear($yearId) : collect();
        $openFlags = $yearId
            ? BehaviorFlag::open()->where('academic_year_id', $yearId)->get(['student_id', 'stage'])->groupBy('stage')
            : collect();

        foreach (BehaviorFlag::STAGES as $stage) {
            $flagged = ($openFlags[$stage] ?? collect())->pluck('student_id')->flip();
            $wouldBeAt = $scores->filter(fn ($score) => $score <= $new[$stage]);

            $result[$stage] = [
                'label' => BehaviorFlag::label($stage),
                'current' => $current[$stage],
                'proposed' => $new[$stage],
                'open' => $flagged->count(),
                'newly_flagged' => $wouldBeAt->keys()->reject(fn ($id) => $flagged->has($id))->count(),
                'unflagged' => $flagged->keys()->reject(fn ($id) => $wouldBeAt->has($id))->count(),
            ];
        }

        return $result;
    }

    private function named(array $byStage): array
    {
        return collect(self::FIELDS)->map(fn ($stage) => $byStage[$stage])->all();
    }
}
