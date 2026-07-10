<?php

namespace App\Services;

use App\Models\ImportedFeeRecord;
use Illuminate\Support\Collection;

class FeeSummaryService
{
    /**
     * One row per student: total billed = sum of imported amounts, balance = most recent
     * record's balance (it's a running balance), paid = billed − balance, status = most
     * recent record's status (status is sourced from the accounting export, not computed here).
     */
    public function studentSummaries(): Collection
    {
        return ImportedFeeRecord::whereNotNull('student_id')
            ->with('student.department')
            ->get()
            ->groupBy('student_id')
            ->map(function ($rows) {
                $latest = $rows->sortByDesc('txn_date')->first();
                $totalBilled = (float) $rows->sum('amount');
                $balance = (float) $latest->balance;

                return (object) [
                    'student' => $latest->student,
                    'total_billed' => $totalBilled,
                    'paid' => $totalBilled - $balance,
                    'balance' => $balance,
                    'status' => $latest->status,
                    'is_restricted' => $rows->contains('is_restricted', true),
                ];
            })
            ->values();
    }

    public function statusDistribution(): array
    {
        $summaries = $this->studentSummaries();
        $paid = $summaries->where('status', 'Paid')->sum('total_billed');
        $partial = $summaries->where('status', 'Partial')->sum('total_billed');
        $outstanding = $summaries->whereIn('status', ['Owed', 'Outstanding'])->sum('total_billed');

        return ['paid' => $paid, 'partial' => $partial, 'outstanding' => $outstanding];
    }

    public function outstandingByDepartment(): Collection
    {
        return $this->studentSummaries()
            ->filter(fn ($s) => $s->balance > 0)
            ->groupBy(fn ($s) => $s->student->department->name ?? 'Unassigned')
            ->map(fn ($rows, $name) => (object) ['department' => $name, 'outstanding' => $rows->sum('balance')])
            ->values()
            ->sortByDesc('outstanding')
            ->values();
    }

    public function collectionRateByPeriod(): Collection
    {
        return ImportedFeeRecord::whereNotNull('student_id')
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
