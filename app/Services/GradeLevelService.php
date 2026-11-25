<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\GradeLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Admin's grade levels (sdp_doc/YASIS_Academic_and_Fee_Rules.md §1): add, rename, reorder,
 * retire and restore. The code is fixed once created, because the school's rules are keyed by it.
 * Nothing is deleted; a level in use by any section can't be retired. Every change is written
 * together with its audit row in one transaction.
 */
class GradeLevelService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function create(array $data, User $actor): GradeLevel
    {
        return DB::transaction(function () use ($data, $actor) {
            $next = ((int) GradeLevel::lockForUpdate()->max('sort_order')) + 1;
            $level = GradeLevel::create(['code' => $data['code'], 'name' => $data['name'], 'sort_order' => $next]);
            $this->audit->log($actor, 'Created grade level', 'GradeLevel', $level->id, ['code' => $level->code, 'name' => $level->name]);

            return $level;
        });
    }

    public function rename(GradeLevel $level, string $name, User $actor): void
    {
        if ($level->name === $name) {
            throw new ActionRefused('Nothing was changed.');
        }

        DB::transaction(function () use ($level, $name, $actor) {
            $from = $level->name;
            $level->update(['name' => $name]);
            $this->audit->log($actor, 'Renamed grade level', 'GradeLevel', $level->id, ['code' => $level->code, 'from' => $from, 'to' => $name]);
        });
    }

    /** Swap the level with its neighbour above ('up') or below ('down') in the school order. */
    public function move(GradeLevel $level, string $direction, User $actor): void
    {
        DB::transaction(function () use ($level, $direction, $actor) {
            $level = GradeLevel::lockForUpdate()->findOrFail($level->id);
            $neighbour = GradeLevel::lockForUpdate()
                ->where('sort_order', $direction === 'up' ? '<' : '>', $level->sort_order)
                ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
                ->first();

            if (! $neighbour) {
                throw new ActionRefused("{$level->name} is already ".($direction === 'up' ? 'first' : 'last').'.');
            }

            // sort_order is unique, so park one row on a free value while the two swap.
            [$mine, $theirs] = [$level->sort_order, $neighbour->sort_order];
            $level->update(['sort_order' => ((int) GradeLevel::min('sort_order')) - 1]);
            $neighbour->update(['sort_order' => $mine]);
            $level->update(['sort_order' => $theirs]);

            $this->audit->log($actor, 'Moved grade level', 'GradeLevel', $level->id, [
                'code' => $level->code,
                'direction' => $direction,
                'order' => GradeLevel::ordered()->pluck('code')->all(),
            ]);
        });
    }

    public function retire(GradeLevel $level, User $actor): void
    {
        DB::transaction(function () use ($level, $actor) {
            $level = GradeLevel::lockForUpdate()->findOrFail($level->id);

            if ($level->isArchived()) {
                throw new ActionRefused("{$level->name} is already retired.");
            }

            // Any section, archived ones included: their history still depends on the level.
            if ($count = $level->sections()->count()) {
                throw new ActionRefused("{$level->name} can't be retired because {$count} ".($count === 1 ? 'section uses' : 'sections use')
                    .' it. Move those sections to another grade level first.');
            }

            $level->update(['archived_at' => now()]);
            $this->audit->log($actor, 'Retired grade level', 'GradeLevel', $level->id, ['code' => $level->code]);
        });
    }

    public function restore(GradeLevel $level, User $actor): void
    {
        if (! $level->isArchived()) {
            throw new ActionRefused("{$level->name} is not retired.");
        }

        DB::transaction(function () use ($level, $actor) {
            $level->update(['archived_at' => null]);
            $this->audit->log($actor, 'Restored grade level', 'GradeLevel', $level->id, ['code' => $level->code]);
        });
    }
}
