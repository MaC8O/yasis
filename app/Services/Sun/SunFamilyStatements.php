<?php

namespace App\Services\Sun;

use App\Exceptions\ActionRefused;
use App\Models\Student;
use App\Models\SunAccountBalance;
use App\Models\SunAccountLink;
use App\Models\SunFamilyStatement;
use App\Models\SunImportBatch;
use App\Models\SunLedgerLine;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * What families see of the Sun ledger: one statement per account and financial year, built when an
 * import is published. Families see every line (T4), except lines the Treasurer hid by hand, which
 * are shown together as one "Adjustments" amount so the totals still equal the ledger. Families
 * read the statement of the latest published import for each year, so an unpublished import
 * changes nothing for them; unpublishing or reverting falls back to the previous one.
 *
 * Content: year_label, opening, lines (visible: id, month, memo, category, amount), hidden (the
 * same, for staff allowed to see them; never rendered to families), adjustments, charges,
 * credits, closing. Amounts are signed: a charge positive, a payment or allowance negative.
 */
class SunFamilyStatements
{
    public function __construct(private SunLinks $links) {}

    /** Build the statements of a batch's year and account range, at publish. Refused if any account doesn't add up. */
    public function buildForBatch(SunImportBatch $batch): int
    {
        $fy = $batch->fiscal_year;
        $balances = SunAccountBalance::current()->where('fiscal_year', $fy)->with('account')
            ->whereHas('account', fn ($q) => $q->where('is_control', false)->where('not_isms_student', false)
                ->whereBetween('code', [$batch->account_code_from, $batch->account_code_to]))->get();
        $ids = $balances->pluck('sun_account_id');
        $students = $this->links->forYear($fy, $ids);
        $lines = SunLedgerLine::current()->where('fiscal_year', $fy)->whereIn('sun_account_id', $ids)
            ->orderBy('period')->orderBy('id')->get()->groupBy('sun_account_id');

        foreach ($balances as $balance) {
            $content = $this->content($balance, $lines[$balance->sun_account_id] ?? collect());
            SunFamilyStatement::updateOrCreate(
                ['sun_account_id' => $balance->sun_account_id, 'fiscal_year' => $fy, 'batch_id' => $batch->id],
                ['student_id' => $students[$balance->sun_account_id] ?? null, 'content' => $content, 'closing_balance' => $content['closing']],
            );
        }

        return $balances->count();
    }

    /** One account's statement for a year, from the current ledger. */
    public function content(SunAccountBalance $balance, Collection $lines): array
    {
        $row = fn (SunLedgerLine $l) => [
            'id' => $l->id, 'month' => $l->period_month->toDateString(), 'memo' => $l->memo,
            'category' => $l->category, 'amount' => (string) $l->amount,
        ];
        $content = [
            'year_label' => SunPeriods::label($balance->fiscal_year),
            'opening' => (string) $balance->opening_balance,
            'closing' => (string) $balance->closing_balance,
            'lines' => $lines->reject->is_restricted->map($row)->values()->all(),
            'hidden' => $lines->filter->is_restricted->map($row)->values()->all(),
        ];
        $content = $this->totals($content);

        $sum = BigDecimal::of($content['opening']);
        foreach ([...$content['lines'], ...$content['hidden']] as $l) {
            $sum = $sum->plus($l['amount']);
        }
        if (! $sum->isEqualTo($content['closing'])) {
            throw new ActionRefused("Account {$balance->account->code}, {$content['year_label']}: the lines don't add up to the closing balance, so nothing was published.");
        }

        return $content;
    }

    /** Recompute adjustments (the hidden lines' total), charges and credits. */
    private function totals(array $content): array
    {
        $sum = fn (array $lines, ?callable $keep = null) => (string) array_reduce(
            array_filter($lines, $keep ?? fn () => true),
            fn (BigDecimal $s, $l) => $s->plus($l['amount']), BigDecimal::zero()->toScale(2));

        $content['adjustments'] = $sum($content['hidden']);
        $content['charges'] = $sum($content['lines'], fn ($l) => BigDecimal::of($l['amount'])->isPositive());
        $content['credits'] = (string) BigDecimal::of($sum($content['lines'], fn ($l) => BigDecimal::of($l['amount'])->isNegative()))->negated();

        return $content;
    }

    // --- Reading ------------------------------------------------------------------------------------------

    /**
     * The latest published statement per account and year (reverted imports left out).
     *
     * @param  iterable<int>|null  $accountIds
     */
    public function latest(?iterable $accountIds = null, ?int $fiscalYear = null): Collection
    {
        $published = SunImportBatch::current()->whereNotNull('published_at')->pluck('id');
        $ids = $accountIds === null ? null : collect($accountIds)->values()->all();

        return SunFamilyStatement::whereIn('batch_id', $published)
            ->when($ids !== null, fn ($q) => $q->whereIn('sun_account_id', $ids))
            ->when($fiscalYear, fn ($q) => $q->where('fiscal_year', $fiscalYear))
            ->get()->groupBy(fn ($s) => $s->sun_account_id.'|'.$s->fiscal_year)
            ->map(fn ($rows) => $rows->sortByDesc('batch_id')->first())->values();
    }

    /** The statements a student's family sees, newest year first. A held year (pending review) shows nothing. */
    public function forStudent(Student $student): Collection
    {
        $accounts = SunAccountLink::active()->where('student_id', $student->id)->pluck('sun_account_id')->unique();

        return $this->latest($accounts)->where('student_id', $student->id)->sortByDesc('fiscal_year')->values();
    }

    /** The latest financial year anything is published for. */
    public function latestYear(): ?int
    {
        return SunImportBatch::current()->whereNotNull('published_at')->max('fiscal_year');
    }

    // --- Keeping published statements in step ---------------------------------------------------------------

    /** After a link or review change: whoever now owns the year's lines, on every statement of that account and year. */
    public function reassign(int $accountId, ?int $fiscalYear = null): void
    {
        $years = $fiscalYear ? [$fiscalYear] : SunFamilyStatement::where('sun_account_id', $accountId)->distinct()->pluck('fiscal_year')->all();
        foreach ($years as $fy) {
            SunFamilyStatement::where('sun_account_id', $accountId)->where('fiscal_year', $fy)
                ->update(['student_id' => $this->links->studentFor($accountId, $fy)]);
        }
    }

    /** A line's category or visibility changed by hand: change it in every statement that holds it. */
    public function refreshLine(SunLedgerLine $line): void
    {
        foreach (SunFamilyStatement::where('sun_account_id', $line->sun_account_id)->where('fiscal_year', $line->fiscal_year)->get() as $statement) {
            $content = $statement->content;
            $all = collect([...$content['lines'], ...$content['hidden']]);
            $found = $all->firstWhere('id', $line->id);
            if (! $found) {
                continue;
            }
            $found['category'] = $line->category;
            $rest = $all->reject(fn ($l) => $l['id'] === $line->id);
            $visible = $rest->filter(fn ($l) => collect($content['lines'])->contains('id', $l['id']));
            $hidden = $rest->filter(fn ($l) => collect($content['hidden'])->contains('id', $l['id']));
            $line->is_restricted ? $hidden->push($found) : $visible->push($found);
            $order = fn ($c) => $c->sortBy([['month', 'asc'], ['id', 'asc']])->values()->all();
            $content['lines'] = $order($visible);
            $content['hidden'] = $order($hidden);
            $statement->update(['content' => $this->totals($content)]);
        }
    }

    /** Erasure: clear every memo in these accounts' statements. Amounts stay. */
    public function scrub(iterable $accountIds): int
    {
        $count = 0;
        foreach (SunFamilyStatement::whereIn('sun_account_id', collect($accountIds)->all())->get() as $statement) {
            $content = $statement->content;
            foreach (['lines', 'hidden'] as $key) {
                $content[$key] = array_map(fn ($l) => array_merge($l, ['memo' => '']), $content[$key]);
            }
            $statement->update(['content' => $content]);
            $count++;
        }

        return $count;
    }
}
