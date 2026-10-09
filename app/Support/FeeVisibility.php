<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see which imported fee rows (§2.2 scope guards, §9.8).
 *
 * Every fee query that leaves the Treasurer's own workspace goes through apply(),
 * and the Visibility Rules page renders rules() — so the page always describes
 * exactly what the code enforces.
 */
final class FeeVisibility
{
    /** Full import data: unpublished, held, unmatched and restricted rows. */
    public const TREASURER = 'treasurer';

    /** Principal / VP Academic / Registrar — `fees.view_readonly`. */
    public const LEADERSHIP = 'leadership';

    /** Guardians — own linked children only (the caller scopes to the child). */
    public const FAMILY = 'family';

    /** Policy switch on the Visibility Rules page; off by default per §2.2. */
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

    /** Constrain an ImportedFeeRecord query to what $audience may see. */
    public static function apply(Builder $query, string $audience): Builder
    {
        if ($audience === self::TREASURER) {
            return $query;
        }

        $query->whereNotNull('student_id')
            ->where('is_held', false)
            ->whereHas('importBatch', fn ($q) => $q->whereNotNull('published_at'));

        $hideRestricted = $audience === self::FAMILY || ! self::leadershipSeesRestricted();

        return $query->when($hideRestricted, fn ($q) => $q->where('is_restricted', false));
    }

    /**
     * Human-readable rule set for the Visibility Rules page, generated from the same
     * conditions apply() enforces.
     *
     * @return array<string, array{label: string, roles: string, sees: list<string>, hidden: list<string>}>
     */
    public static function rules(): array
    {
        $leadershipRestricted = self::leadershipSeesRestricted();

        return [
            self::TREASURER => [
                'label' => 'Treasurer',
                'roles' => 'Treasurer',
                'sees' => ['Every imported row, in every batch', 'Draft (unpublished) batches', 'Unmatched and held rows', 'Restricted (SDA) rows, flagged'],
                'hidden' => [],
            ],
            self::LEADERSHIP => [
                'label' => 'Leadership (read-only)',
                'roles' => 'Principal · VP Academic · Registrar',
                'sees' => array_values(array_filter([
                    'All students, from published batches only',
                    'Matched rows only',
                    $leadershipRestricted ? 'Restricted (SDA) rows, flagged' : null,
                ])),
                'hidden' => array_values(array_filter([
                    'Draft (unpublished) batches',
                    'Unmatched and held rows',
                    $leadershipRestricted ? null : 'Restricted (SDA) rows',
                ])),
            ],
            self::FAMILY => [
                'label' => 'Guardian',
                'roles' => 'Guardian (own linked children)',
                'sees' => ['Their own children only', 'Published, matched rows'],
                'hidden' => ['Other students', 'Draft (unpublished) batches', 'Unmatched and held rows', 'Restricted (SDA) rows — always'],
            ],
        ];
    }
}
