<?php

namespace App\Services\Sun;

use App\Models\FeeCategory;
use Brick\Math\BigDecimal;

/**
 * §5: a ledger line's category from its memo, by the Treasurer's keyword rules (fee_category_rules),
 * checked in order; the first match wins. A rule is checked at its category's position unless it
 * has its own: the "CR credit" rule (any credit in a CR cash-receipt journal is a payment) sits
 * right after brought_forward. No match is "unclassified".
 */
class FeeCategoriser
{
    /** @param  list<array{key: string, keyword: ?string, cr_credit: bool}>  $rules  in the order checked */
    public function __construct(private array $rules, private array $restricted) {}

    public static function fromDatabase(): self
    {
        $categories = FeeCategory::with(['rules' => fn ($q) => $q->where('active', true)->orderBy('id')])->orderBy('position')->get();
        $rules = [];
        foreach ($categories as $c) {
            foreach ($c->rules as $r) {
                $keyword = $r->keyword !== null ? mb_strtolower(trim($r->keyword)) : null;
                if ($r->cr_credit || $keyword !== '') {
                    $rules[] = ['position' => $r->position ?? $c->position, 'category' => $c->position, 'id' => $r->id,
                        'key' => $c->key, 'keyword' => $r->cr_credit ? null : $keyword, 'cr_credit' => (bool) $r->cr_credit];
                }
            }
        }
        usort($rules, fn ($a, $b) => [$a['position'], $a['category'], $a['id']] <=> [$b['position'], $b['category'], $b['id']]);

        return new self($rules, $categories->pluck('restricted_by_default', 'key')->map(fn ($v) => (bool) $v)->all());
    }

    public function classify(string $memo, string $journalType, string $amount): string
    {
        $text = mb_strtolower($memo);
        $crCredit = strtoupper($journalType) === 'CR' && BigDecimal::of($amount)->isNegative();
        foreach ($this->rules as $rule) {
            if ($rule['cr_credit'] ? $crCredit : str_contains($text, $rule['keyword'])) {
                return $rule['key'];
            }
        }

        return 'unclassified';
    }

    /** T4: whether a category's lines are hidden from families by default. */
    public function restrictedByDefault(string $category): bool
    {
        return $this->restricted[$category] ?? false;
    }

    private const MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /** §5: the month a memo names ("for Jan 2020", "December, 2019") as Y-m-01, or null. */
    public static function billedMonth(string $memo): ?string
    {
        if (! preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?,?\s*(\d{4})\b/i', $memo, $m)) {
            return null;
        }
        $year = (int) $m[2];

        return $year >= 1990 && $year <= 2100 ? sprintf('%04d-%02d-01', $year, self::MONTHS[strtolower($m[1])]) : null;
    }
}
