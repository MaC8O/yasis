<?php

namespace App\Support;

/**
 * Accounting-style money formatting for the finance screens. Amounts are Myanmar
 * Kyat with no minor unit in practice, so they render as whole numbers with
 * thousands separators; the currency code goes in column headers, not every cell.
 */
final class Money
{
    public const CURRENCY = 'MMK';

    /** 1250000 → "1,250,000"; 0 → "–" when $dashZero (ledger convention for empty cells). */
    public static function format(float|int|string|null $amount, bool $dashZero = false): string
    {
        $amount = (float) $amount;

        if ($dashZero && abs($amount) < 0.005) {
            return '–';
        }

        // Negative values (credits) read as (1,250) in accounting.
        return $amount < 0 ? '('.number_format(abs($amount)).')' : number_format($amount);
    }

    /** 1250000 → "1,250,000 MMK". */
    public static function withCurrency(float|int|string|null $amount): string
    {
        return self::format($amount).' '.self::CURRENCY;
    }
}
