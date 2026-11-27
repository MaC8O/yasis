<?php

namespace App\Support;

use App\Services\Sun\SunPeriods;

/** How the Treasurer's ledger screens show signed amounts: a credit as "1,250.00 Cr", as Sun prints it. */
final class LedgerFormat
{
    public static function money(string|float|int|null $amount): string
    {
        $value = (float) $amount;

        return $value < 0 ? number_format(abs($value), 2).' Cr' : number_format($value, 2);
    }

    public static function year(int $fiscalYear): string
    {
        return SunPeriods::label($fiscalYear);
    }

    /** Owing, Settled or In credit (§9, T6). */
    public static function status(string|float|null $closing): string
    {
        return match ((float) $closing <=> 0.0) {
            1 => 'Owing',
            0 => 'Settled',
            default => 'In credit',
        };
    }
}
