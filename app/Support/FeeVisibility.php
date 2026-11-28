<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;

/**
 * Who sees what of the Sun ledger (§2.2 scope guards, T4). The Visibility Rules page renders
 * rules(), so it describes what the pages do:
 * - the Treasurer: the live ledger, every line, published or not;
 * - leadership (Principal, VP Academic, Registrar): the published statements, read-only; lines
 *   the Treasurer hid by hand only if the policy switch allows, marked;
 * - families (guardians and students): their own published statements, every line (T4), with
 *   lines hidden by hand shown only as one "Adjustments" amount.
 */
final class FeeVisibility
{
    public const TREASURER = 'treasurer';

    public const LEADERSHIP = 'leadership';

    public const FAMILY = 'family';

    /** Policy switch on the Visibility Rules page: leadership sees lines hidden by hand. Off by default (§2.2). */
    public const LEADERSHIP_SEES_RESTRICTED = 'fees.leadership_sees_restricted';

    public static function audienceFor(User $user): string
    {
        return match (true) {
            $user->hasRole('treasurer') => self::TREASURER,
            $user->hasAnyRole(['principal', 'vp_academic', 'registrar']) => self::LEADERSHIP,
            default => self::FAMILY,
        };
    }

    public static function leadershipSeesRestricted(): bool
    {
        return SystemSetting::get(self::LEADERSHIP_SEES_RESTRICTED, '0') === '1';
    }

    /** @return array<string, array{label: string, roles: string, sees: list<string>, hidden: list<string>}> */
    public static function rules(): array
    {
        $leadership = self::leadershipSeesRestricted();

        return [
            self::TREASURER => [
                'label' => 'Treasurer',
                'roles' => 'Treasurer',
                'sees' => ['The live ledger: every import, published or not', 'Every line, including lines hidden by hand (marked)', 'Unmatched accounts, held accounts and Student Control'],
                'hidden' => [],
            ],
            self::LEADERSHIP => [
                'label' => 'Leadership (read-only)',
                'roles' => 'Principal · VP Academic · Registrar',
                'sees' => array_values(array_filter([
                    'Every student\'s published statements, the same as the family sees',
                    $leadership ? 'Lines hidden from families by hand, marked' : null,
                ])),
                'hidden' => array_values(array_filter([
                    'Imports not yet published',
                    'Unmatched and held accounts, Student Control',
                    $leadership ? null : 'Lines hidden by hand (shown as one "Adjustments" amount)',
                ])),
            ],
            self::FAMILY => [
                'label' => 'Families',
                'roles' => 'Guardians (their own children) · Students (themselves)',
                'sees' => ['Their own published statements', 'Every line, allowances included (T4)'],
                'hidden' => ['Other students', 'Imports not yet published', 'A year held as a possible reused code', 'Lines hidden by hand (shown as one "Adjustments" amount)'],
            ],
        ];
    }
}
