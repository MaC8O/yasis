<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Admin's curriculum (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3): add a subject to a grade
 * level, edit a row, reorder, retire and restore. Nothing is deleted. Every change is written
 * together with its audit row in one transaction.
 */
class CurriculumService
{
    /** Fields the Admin edits on a row. */
    private const FIELDS = ['report_label', 'is_compulsory', 'elective_slot', 'credit_hours', 'grading_mode', 'counts_in_result'];

    public function __construct(protected AuditService $audit)
    {
    }

    public function add(GradeLevel $level, array $data, User $actor): SubjectOffering
    {
        return DB::transaction(function () use ($level, $data, $actor) {
            $next = ((int) SubjectOffering::where('grade_level_id', $level->id)->lockForUpdate()->max('sort_order')) + 1;
            $offering = SubjectOffering::create(array_merge(
                $this->normalise($data),
                ['grade_level_id' => $level->id, 'subject_id' => $data['subject_id'], 'sort_order' => $next],
            ));
            $this->audit->log($actor, 'Added subject to curriculum', 'SubjectOffering', $offering->id, [
                'grade_level' => $level->code,
                'subject' => $offering->subject->code,
                'values' => $offering->only(self::FIELDS),
            ]);

            return $offering;
        });
    }

    /**
     * Edit a row. `confirmed` (the "Confirmed by the school" tick) clears the row's pending
     * question flag.
     */
    public function update(SubjectOffering $offering, array $data, User $actor): void
    {
        DB::transaction(function () use ($offering, $data, $actor) {
            $offering->fill($this->normalise($data));
            if (! empty($data['confirmed'])) {
                $offering->pending_question = null;
            }

            $changes = collect($offering->getDirty())
                ->map(fn ($to, $field) => ['from' => $offering->getOriginal($field), 'to' => $to])
                ->all();
            if ($changes === []) {
                throw new ActionRefused('Nothing was changed.');
            }

            $offering->save();
            $this->audit->log($actor, 'Updated curriculum row', 'SubjectOffering', $offering->id, [
                'grade_level' => $offering->gradeLevel->code,
                'subject' => $offering->subject->code,
                'changes' => $changes,
            ]);
        });
    }

    /** Swap the row with its neighbour above ('up') or below ('down') in the grade level's order. */
    public function move(SubjectOffering $offering, string $direction, User $actor): void
    {
        DB::transaction(function () use ($offering, $direction, $actor) {
            $offering = SubjectOffering::lockForUpdate()->findOrFail($offering->id);
            $siblings = SubjectOffering::where('grade_level_id', $offering->grade_level_id)->lockForUpdate();
            $neighbour = (clone $siblings)
                ->where('sort_order', $direction === 'up' ? '<' : '>', $offering->sort_order)
                ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
                ->first();

            if (! $neighbour) {
                throw new ActionRefused("{$offering->report_label} is already ".($direction === 'up' ? 'first' : 'last').'.');
            }

            // sort_order is unique within the level, so park one row on a free value while they swap.
            [$mine, $theirs] = [$offering->sort_order, $neighbour->sort_order];
            $offering->update(['sort_order' => ((int) (clone $siblings)->min('sort_order')) - 1]);
            $neighbour->update(['sort_order' => $mine]);
            $offering->update(['sort_order' => $theirs]);

            $this->audit->log($actor, 'Moved curriculum row', 'SubjectOffering', $offering->id, [
                'grade_level' => $offering->gradeLevel->code,
                'subject' => $offering->subject->code,
                'direction' => $direction,
                'order' => SubjectOffering::where('grade_level_id', $offering->grade_level_id)->ordered()
                    ->with('subject')->get()->pluck('subject.code')->all(),
            ]);
        });
    }

    public function retire(SubjectOffering $offering, User $actor): void
    {
        DB::transaction(function () use ($offering, $actor) {
            $offering = SubjectOffering::lockForUpdate()->findOrFail($offering->id);

            if ($offering->isArchived()) {
                throw new ActionRefused("{$offering->report_label} is already retired from this grade level.");
            }

            // A student's elective choice for the current year would point at a retired row.
            $activeYearId = AcademicYear::where('is_active', true)->value('id');
            if ($count = $offering->electiveChoices()->where('academic_year_id', $activeYearId)->count()) {
                throw new ActionRefused("{$offering->report_label} can't be retired: {$count} ".($count === 1 ? 'student has' : 'students have')
                    .' chosen it this year. Change their elective choice first.');
            }

            $offering->update(['archived_at' => now()]);
            $this->audit->log($actor, 'Retired curriculum row', 'SubjectOffering', $offering->id, [
                'grade_level' => $offering->gradeLevel->code,
                'subject' => $offering->subject->code,
            ]);
        });
    }

    public function restore(SubjectOffering $offering, User $actor): void
    {
        if (! $offering->isArchived()) {
            throw new ActionRefused("{$offering->report_label} is not retired.");
        }

        DB::transaction(function () use ($offering, $actor) {
            $offering->update(['archived_at' => null]);
            $this->audit->log($actor, 'Restored curriculum row', 'SubjectOffering', $offering->id, [
                'grade_level' => $offering->gradeLevel->code,
                'subject' => $offering->subject->code,
            ]);
        });
    }

    /** A compulsory row has no slot; credit hours are stored with one decimal or not at all. */
    private function normalise(array $data): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('is_compulsory', $values) && $values['is_compulsory']) {
            $values['elective_slot'] = null;
        }
        if (array_key_exists('credit_hours', $values)) {
            // Validated as a plain decimal string ("2" or "1.5"); stored as DECIMAL(3,1), no float.
            $values['credit_hours'] = $values['credit_hours'] === null || $values['credit_hours'] === '' ? null : (string) $values['credit_hours'];
        }

        return $values;
    }
}
