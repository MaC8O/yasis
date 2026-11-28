<?php

namespace App\Services\Sun;

use App\Models\SunAccountLink;
use App\Models\SunAccountReview;

/**
 * Which student a ledger account's lines belong to in a financial year (T3): the student whose
 * link covers that year, unless a "possible reused code" review is pending for that year, in
 * which case nobody (the lines are held for the Treasurer).
 */
class SunLinks
{
    public function studentFor(int $accountId, int $fiscalYear): ?int
    {
        if (SunAccountReview::pending()->where('sun_account_id', $accountId)->where('fiscal_year', $fiscalYear)->exists()) {
            return null;
        }

        return SunAccountLink::covering($fiscalYear)->where('sun_account_id', $accountId)->value('student_id');
    }

    /**
     * Every linked account's student for a year, held accounts left out.
     *
     * @param  iterable<int>|null  $accountIds  limit to these accounts
     * @return array<int, int> account id => student id
     */
    public function forYear(int $fiscalYear, ?iterable $accountIds = null): array
    {
        $ids = $accountIds === null ? null : collect($accountIds)->values()->all();
        $held = SunAccountReview::pending()->where('fiscal_year', $fiscalYear)
            ->when($ids !== null, fn ($q) => $q->whereIn('sun_account_id', $ids))->pluck('sun_account_id')->flip();

        return SunAccountLink::covering($fiscalYear)->when($ids !== null, fn ($q) => $q->whereIn('sun_account_id', $ids))
            ->pluck('student_id', 'sun_account_id')->reject(fn ($s, $a) => $held->has($a))->all();
    }

    /** Account ids held in a year by a pending "possible reused code" review. */
    public function held(int $fiscalYear): array
    {
        return SunAccountReview::pending()->where('fiscal_year', $fiscalYear)->pluck('sun_account_id')->all();
    }

    /** Names compared ignoring case, surrounding and repeated spaces (Sun cuts names off at about 30 characters). */
    public static function sameName(?string $a, ?string $b): bool
    {
        return self::normalise($a) === self::normalise($b);
    }

    public static function normalise(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }
}
