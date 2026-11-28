<?php

namespace App\Services\Sun;

use App\Models\Student;
use App\Models\SunAccountBalance;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fee figures for staff pages. Leadership (Principal, VP, Registrar) and the Principal's
 * dashboard read the published family statements, the same figures families see. The
 * Treasurer's reports read the live ledger, published or not.
 */
class SunFeeOverview
{
    public function __construct(private SunFamilyStatements $statements, private SunLinks $links) {}

    /**
     * One row per student with a published statement for the year (the latest year by default):
     * student, section, statement, closing, status.
     */
    public function publishedRows(?int $fiscalYear = null): Collection
    {
        $fiscalYear ??= $this->statements->latestYear();
        if (! $fiscalYear) {
            return collect();
        }
        $statements = $this->statements->latest(null, $fiscalYear)->whereNotNull('student_id');
        $students = Student::with(['department', 'enrollments.section.gradeLevel'])->whereIn('id', $statements->pluck('student_id'))->get()->keyBy('id');

        return $statements->map(fn ($s) => (object) [
            'statement' => $s,
            'student' => $students[$s->student_id],
            'section' => $students[$s->student_id]->enrollments->firstWhere('status', 'Active')?->section,
            'closing' => (float) $s->closing_balance,
            'status' => $s->status(),
        ])->sortBy(fn ($r) => $r->student->name)->values();
    }

    /** @return array{Owing: int, Settled: int, 'In credit': int} */
    public function distribution(Collection $rows): array
    {
        return ['Owing' => $rows->where('status', 'Owing')->count(), 'Settled' => $rows->where('status', 'Settled')->count(),
            'In credit' => $rows->where('status', 'In credit')->count()];
    }

    /** @return array{owing: float, credit: float} what is owed (positive balances) and held in credit (negative ones) */
    public function totals(Collection $rows): array
    {
        return ['owing' => $rows->where('closing', '>', 0)->sum('closing'), 'credit' => abs($rows->where('closing', '<', 0)->sum('closing'))];
    }

    /** By a key (department, grade): how many owe, settle and are in credit, and the amounts. */
    public function groupBy(Collection $rows, callable $key): Collection
    {
        return $rows->groupBy($key)->map(fn ($group, $name) => (object) [
            'name' => $name ?: 'Not enrolled this year',
            'owing' => $group->where('status', 'Owing')->count(), 'settled' => $group->where('status', 'Settled')->count(),
            'credit' => $group->where('status', 'In credit')->count(),
            'owing_total' => $group->where('closing', '>', 0)->sum('closing'), 'credit_total' => abs($group->where('closing', '<', 0)->sum('closing')),
        ])->sortBy('name')->values();
    }

    // --- The Treasurer's live figures ------------------------------------------------------------------

    /** Live rows for a year: every matched account (published or not), with its student. */
    public function liveRows(int $fiscalYear): Collection
    {
        $balances = SunAccountBalance::current()->where('fiscal_year', $fiscalYear)->where('is_control', false)->get()->keyBy('sun_account_id');
        $owners = $this->links->forYear($fiscalYear, $balances->keys());
        $students = Student::with(['department', 'enrollments.section.gradeLevel'])->whereIn('id', array_values($owners))->get()->keyBy('id');

        return collect($owners)->map(function ($studentId, $accountId) use ($balances, $students) {
            $student = $students[$studentId];
            $closing = (float) $balances[$accountId]->closing_balance;

            return (object) [
                'student' => $student, 'section' => $student->enrollments->firstWhere('status', 'Active')?->section,
                'balance' => $balances[$accountId], 'closing' => $closing, 'status' => $balances[$accountId]->status(),
            ];
        })->sortBy(fn ($r) => $r->student->name)->values();
    }

    /**
     * Charges, payments and allowances by month for a year (student accounts only): debits,
     * allowance credits, payment credits and other credits.
     */
    public function byMonth(int $fiscalYear): Collection
    {
        return SunLedgerLine::current()->where('fiscal_year', $fiscalYear)
            ->whereIn('sun_account_id', SunAccountBalance::where('fiscal_year', $fiscalYear)->where('is_control', false)->select('sun_account_id'))
            ->selectRaw("period_month,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS charges,
                SUM(CASE WHEN amount < 0 AND category = 'payment' THEN -amount ELSE 0 END) AS payments,
                SUM(CASE WHEN amount < 0 AND category = 'allowance' THEN -amount ELSE 0 END) AS allowances,
                SUM(CASE WHEN amount < 0 AND category NOT IN ('payment', 'allowance') THEN -amount ELSE 0 END) AS other_credits")
            ->groupBy('period_month')->orderBy('period_month')->get();
    }

    /** Debits and credits by category for a year (student accounts only). */
    public function byCategory(int $fiscalYear): Collection
    {
        return SunLedgerLine::current()->where('fiscal_year', $fiscalYear)
            ->whereIn('sun_account_id', SunAccountBalance::where('fiscal_year', $fiscalYear)->where('is_control', false)->select('sun_account_id'))
            ->select('category', DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS debits'), DB::raw('SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) AS credits'), DB::raw('COUNT(*) AS line_count'))
            ->groupBy('category')->orderBy('category')->get();
    }

    /** The Student Control balance at each import (T7). */
    public function controlHistory(): Collection
    {
        return SunImportBatch::current()->orderBy('id')->get(['id', 'source_file', 'fiscal_year', 'control_balance', 'created_at', 'published_at']);
    }
}
