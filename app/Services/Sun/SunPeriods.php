<?php

namespace App\Services\Sun;

use Carbon\CarbonImmutable;

/**
 * Sun accounting periods (§1, §4). The financial year is named by the year it ends in; period 1 is
 * the first month of the financial year (April by default, T2), so with April, period 9 of 2020 is
 * December 2019 and period 10 is January 2020.
 */
final class SunPeriods
{
    /** The first day of the calendar month a period stands for, as Y-m-d. */
    public static function month(int $period, int $fiscalYear, int $firstMonth = 4): string
    {
        $month = (($firstMonth - 1 + $period - 1) % 12) + 1;
        $year = $firstMonth === 1 || $month < $firstMonth ? $fiscalYear : $fiscalYear - 1;

        return sprintf('%04d-%02d-01', $year, $month);
    }

    /**
     * A typed date more than one month away from its period's month (§4): before the first day of
     * the month before, or after the last day of the month after.
     */
    public static function isSuspect(?string $typedDate, string $periodMonth): bool
    {
        if ($typedDate === null) {
            return true;
        }
        $start = CarbonImmutable::parse($periodMonth);
        $date = CarbonImmutable::parse($typedDate);

        return $date->lt($start->subMonthNoOverflow()) || $date->gt($start->addMonthsNoOverflow(2)->subDay());
    }

    /** "2020" as "2019-2020". */
    public static function label(int $fiscalYear): string
    {
        return ($fiscalYear - 1).'-'.$fiscalYear;
    }
}
