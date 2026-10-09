<?php

namespace App\Services;

use App\Models\ImportedFeeRecord;
use App\Support\FeeVisibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FeeSummaryService
{
    /** Receivables aging buckets, by days since the unpaid charge's date. */
    public const AGING_BUCKETS = [
        'current' => 'Current (0–30)',
        'days_31_60' => '31–60 days',
        'days_61_90' => '61–90 days',
        'over_90' => 'Over 90 days',
    ];

    /**
     * One row per student. Each imported line is a charge (an "open item"): `amount` is what was
     * billed, `balance` is what is still unpaid on that line, and the export's per-line `status`
     * says Paid / Partial / Outstanding. So the account balance is the sum of the open amounts,
     * paid = billed − balance, and the account status follows from those totals.
     *
     * $audience limits the rows to what that audience may see (see FeeVisibility).
     */
    public function studentSummaries(string $audience = FeeVisibility::TREASURER): Collection
    {
        return ImportedFeeRecord::query()
            ->visibleTo($audience)
            ->whereNotNull('student_id')
            ->with(['student.department', 'student.enrollments.section'])
            ->get()
            ->groupBy('student_id')
            ->map(function ($rows) {
                $lines = $this->statementLines($rows);
                $student = $rows->first()->student;
                $totalBilled = (float) $lines->sum('charge');
                $balance = (float) $lines->sum('open');

                return (object) [
                    'student' => $student,
                    'section' => $student->enrollments->firstWhere('status', 'Active')?->section,
                    'total_billed' => $totalBilled,
                    'paid' => $totalBilled - $balance,
                    'balance' => $balance,
                    'status' => $this->accountStatus($totalBilled, $balance),
                    'last_activity' => $lines->last()->record->txn_date,
                    'lines' => $rows->count(),
                    'is_restricted' => $rows->contains('is_restricted', true),
                    'aging' => $this->aging($lines),
                ];
            })
            ->values();
    }

    public function accountStatus(float $billed, float $balance): string
    {
        return match (true) {
            $balance <= 0 => 'Paid',
            $balance < $billed => 'Partial',
            default => 'Outstanding',
        };
    }

    /**
     * A student's rows as statement-of-account lines, oldest first: what was charged, what has
     * been paid against it, what is still open on it, and the running balance of open items.
     *
     * @return Collection<int, object{record: ImportedFeeRecord, charge: float, payment: float, open: float, balance: float}>
     */
    public function statementLines(Collection $records): Collection
    {
        $running = 0.0;

        return $records->sortBy([['txn_date', 'asc'], ['id', 'asc']])->values()->map(function ($record) use (&$running) {
            $charge = (float) $record->amount;
            $open = max(0.0, (float) $record->balance);
            $running += $open;

            return (object) [
                'record' => $record,
                'charge' => $charge,
                'payment' => max(0.0, $charge - $open),
                'open' => $open,
                'balance' => $running,
            ];
        });
    }

    /**
     * Ages the outstanding balance: each line's open amount is aged from that line's date.
     *
     * @return array<string, float> keyed by AGING_BUCKETS
     */
    public function aging(Collection $lines, ?Carbon $asOf = null): array
    {
        $asOf ??= today();
        $buckets = array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);

        foreach ($lines as $line) {
            if ($line->open <= 0) {
                continue;
            }
            $days = (int) $line->record->txn_date->diffInDays($asOf, false);
            $bucket = match (true) {
                $days <= 30 => 'current',
                $days <= 60 => 'days_31_60',
                $days <= 90 => 'days_61_90',
                default => 'over_90',
            };
            $buckets[$bucket] += $line->open;
        }

        return $buckets;
    }

    /** Totals per aging bucket across $summaries. */
    public function agingTotals(Collection $summaries): array
    {
        return collect(array_keys(self::AGING_BUCKETS))
            ->mapWithKeys(fn ($key) => [$key => (float) $summaries->sum(fn ($s) => $s->aging[$key])])
            ->all();
    }

    public function statusDistribution(string $audience = FeeVisibility::TREASURER): array
    {
        $summaries = $this->studentSummaries($audience);
        $paid = $summaries->where('status', 'Paid')->sum('total_billed');
        $partial = $summaries->where('status', 'Partial')->sum('total_billed');
        $outstanding = $summaries->whereIn('status', ['Owed', 'Outstanding'])->sum('total_billed');

        return ['paid' => $paid, 'partial' => $partial, 'outstanding' => $outstanding];
    }

    public function outstandingByDepartment(string $audience = FeeVisibility::TREASURER, ?Collection $summaries = null): Collection
    {
        return ($summaries ?? $this->studentSummaries($audience))
            ->filter(fn ($s) => $s->balance > 0)
            ->groupBy(fn ($s) => $s->student->department->name ?? 'Unassigned')
            ->map(fn ($rows, $name) => (object) ['department' => $name, 'outstanding' => $rows->sum('balance'), 'students' => $rows->count()])
            ->values()
            ->sortByDesc('outstanding')
            ->values();
    }

    public function collectionRateByPeriod(string $audience = FeeVisibility::TREASURER): Collection
    {
        return ImportedFeeRecord::query()
            ->visibleTo($audience)
            ->whereNotNull('student_id')
            ->with('importBatch')
            ->get()
            ->groupBy(fn ($r) => $r->importBatch->period)
            ->map(function ($rows, $period) {
                $billed = (float) $rows->sum('amount');
                $balance = (float) $rows->sum('balance');
                $rate = $billed > 0 ? round((($billed - $balance) / $billed) * 100, 1) : 0;

                return (object) ['period' => $period, 'rate' => $rate];
            })
            ->values();
    }
}
