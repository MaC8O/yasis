<?php

namespace App\Services\Sun;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Student;
use App\Models\SunAccount;
use App\Models\SunLedgerLine;
use App\Services\FeeScheduleService;
use Brick\Math\BigDecimal;

/**
 * §10 of the rules file: flag (never refuse) a student whose imported charges for a year don't
 * match the fee schedule for their grade, for the Treasurer to review. Compares the year's
 * totals only, so it assumes neither monthly nor instalment charging (T10):
 * - tuition matches the year's amount, or a whole number of its instalments (a student who joined mid-year);
 * - the yearly fee matches its amount;
 * - a deposit, when charged, matches its amount (it is for new students only, so none is fine).
 * With no schedule for the ledger's year, nothing is flagged.
 */
class SunScheduleCheck
{
    public function __construct(private FeeScheduleService $schedules, private SunLinks $links) {}

    /** @return array{label: string, has_schedule: bool, rows: list<array>} */
    public function forYear(int $fiscalYear): array
    {
        $label = SunPeriods::label($fiscalYear);
        $year = AcademicYear::where('year_label', $label)->first();
        if (! $year || ! FeeSchedule::where('academic_year_id', $year->id)->exists()) {
            return ['label' => $label, 'has_schedule' => false, 'rows' => []];
        }

        $charges = SunLedgerLine::current()->where('fiscal_year', $fiscalYear)->where('amount', '>', 0)
            ->whereIn('category', ['tuition', 'yearly', 'deposit'])
            ->selectRaw('sun_account_id, category, SUM(amount) AS total')->groupBy('sun_account_id', 'category')->get()
            ->groupBy('sun_account_id')->map(fn ($rows) => $rows->pluck('total', 'category'));
        $reversals = SunLedgerLine::current()->where('fiscal_year', $fiscalYear)->where('category', 'reversal')
            ->selectRaw('sun_account_id, COUNT(*) AS n')->groupBy('sun_account_id')->pluck('n', 'sun_account_id');
        // The student each account's lines belong to this year (held accounts are left out).
        $students = $this->links->forYear($fiscalYear, SunLedgerLine::current()->where('fiscal_year', $fiscalYear)->distinct()->pluck('sun_account_id'));
        $accounts = SunAccount::whereIn('id', array_keys($students))->get();
        $studentModels = Student::whereIn('id', array_values($students))->get()->keyBy('id');

        $rows = [];
        foreach ($accounts as $account) {
            $student = $studentModels[$students[$account->id]];
            $account->setRelation('student', $student);
            $level = Enrollment::with('section.gradeLevel')->where('student_id', $student->id)->where('status', '!=', 'Withdrawn')
                ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))->latest('id')->first()?->section?->gradeLevel;
            $base = ['account' => $account, 'grade' => $level?->name, 'reversals' => (int) ($reversals[$account->id] ?? 0)];
            if (! $level) {
                $rows[] = $base + ['issues' => ["No grade for {$label}, so the charges can't be checked."]];

                continue;
            }
            $expected = $this->schedules->forLevel($level, $year) ?? [];
            $charged = $charges[$account->id] ?? collect();
            $issues = [];
            foreach (['tuition', 'yearly', 'deposit'] as $type) {
                if (! isset($expected[$type])) {
                    continue;
                }
                $got = BigDecimal::of((string) ($charged[$type] ?? '0'))->toScale(0, \Brick\Math\RoundingMode::DOWN);
                if (! $this->matches($type, $got, $expected[$type])) {
                    $issues[] = ['fee' => $expected[$type]['label'], 'expected' => $expected[$type]['amount'], 'charged' => (string) $got];
                }
            }
            if ($issues) {
                $rows[] = $base + ['issues' => $issues];
            }
        }

        return ['label' => $label, 'has_schedule' => true, 'rows' => $rows];
    }

    private function matches(string $type, BigDecimal $charged, array $line): bool
    {
        $amount = BigDecimal::of($line['amount']);
        if ($charged->isEqualTo($amount)) {
            return true;
        }
        if ($type === 'deposit') {
            return $charged->isZero();
        }
        if ($type === 'tuition') {
            $sum = BigDecimal::zero();
            foreach ($line['per_instalment'] as $part) {
                $sum = $sum->plus($part);
                if ($charged->isEqualTo($sum)) {
                    return true;
                }
            }
        }

        return false;
    }
}
