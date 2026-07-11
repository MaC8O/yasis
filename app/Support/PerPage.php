<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Shared resolver for the "show N per page" list control (15 / 20 / 30 / 50 / All).
 * Controllers call resolve() to get the paginate() size; the <x-per-page> Blade
 * component renders the matching selector and preserves the other query filters.
 */
class PerPage
{
    /** Selectable page sizes offered on every list. */
    public const OPTIONS = [15, 20, 30, 50];

    /** Row count used to mean "All" (large enough for any single school's list). */
    public const ALL = 100000;

    public static function resolve(Request $request, int $default = 15): int
    {
        $value = $request->input('per_page');

        if ($value === 'all') {
            return self::ALL;
        }

        $value = (int) $value;

        return in_array($value, self::OPTIONS, true) ? $value : $default;
    }
}
