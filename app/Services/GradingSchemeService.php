<?php

namespace App\Services;

use App\Exceptions\ActionRefused;
use App\Models\GradeLevel;
use App\Models\GradingScheme;
use App\Models\GradingSchemeBand;
use App\Models\User;
use App\Support\GradingBands;
use Illuminate\Support\Facades\DB;

/**
 * The Admin's grading schemes (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4-6): edit a scheme's
 * settings, replace one of its band sets, and choose the scheme a grade level uses. Band sets are
 * never edited in place: the old set is archived and the new one inserted at the same moment, so
 * phase 4 can find the set in force when a term was locked. Every change is written together with
 * its audit row in one transaction.
 */
class GradingSchemeService
{
    /** Settings the Admin edits on the scheme itself. Rounding precision and range top go with the band sets. */
    private const SETTINGS = ['name', 'rounding_mode', 'subject_fail_rule', 'failed_ranking'];

    public function __construct(protected AuditService $audit) {}

    /**
     * The problems with a band set if it were saved with these decimals (and, for the result set,
     * this range top). Empty when valid. Used by the FormRequest and again here before saving.
     *
     * @return list<string>
     */
    public function problems(GradingScheme $scheme, string $kind, array $bands, int $decimals, ?string $resultMax = null): array
    {
        $top = $kind === 'result' && $resultMax !== null && $scheme->hasEditableResultMax() ? $resultMax : $scheme->rangeMax($kind);
        $usesValues = $kind === 'mark' && $scheme->usesBandValues();

        if ($kind === 'result' && $scheme->hasEditableResultMax()) {
            $topH = GradingBands::hundredths($top);
            if ($topH === null || $topH === 0 || $topH > 10000) {
                return ['The top of the range must be a number above 0 and at most 100.'];
            }
            if ($topH % (10 ** (2 - $decimals)) !== 0) {
                return ['The top of the range must use the same decimal places as the bands.'];
            }
        }

        return GradingBands::problems(
            $bands,
            $decimals,
            $top,
            $usesValues ? $scheme->valueLabel() : null,
            $usesValues && $scheme->result_basis === 'gpa' ? (string) $scheme->result_max : null,
        );
    }

    /** Edit the scheme's name, rounding mode and subject-fail rule; `confirmed` clears its pending flag. */
    public function updateSettings(GradingScheme $scheme, array $data, User $actor): void
    {
        DB::transaction(function () use ($scheme, $data, $actor) {
            $scheme = GradingScheme::lockForUpdate()->findOrFail($scheme->id);
            $scheme->fill(array_intersect_key($data, array_flip(self::SETTINGS)));
            if (! empty($data['confirmed'])) {
                $scheme->pending_question = null;
            }

            $changes = collect($scheme->getDirty())
                ->map(fn ($to, $field) => ['from' => $scheme->getOriginal($field), 'to' => $to])
                ->all();
            if ($changes === []) {
                throw new ActionRefused('Nothing was changed.');
            }

            $scheme->save();
            $this->audit->log($actor, 'Updated grading scheme', 'GradingScheme', $scheme->id, [
                'scheme' => $scheme->code,
                'changes' => $changes,
            ]);
        });
    }

    /**
     * Replace one band set (`mark` or `result`) with its decimal places and, for the result set,
     * the top of its range. A band keeps its pending-question flag when a band with the same label
     * is saved again, unless `confirmed` (the "Confirmed by the school" tick) is set.
     *
     * @param  array{decimals: int, result_max?: string|null, bands: array, confirmed?: bool}  $data
     */
    public function saveBands(GradingScheme $scheme, string $kind, array $data, User $actor): void
    {
        DB::transaction(function () use ($scheme, $kind, $data, $actor) {
            $scheme = GradingScheme::lockForUpdate()->findOrFail($scheme->id);
            $decimals = (int) $data['decimals'];
            $resultMax = $kind === 'result' && $scheme->hasEditableResultMax() ? ($data['result_max'] ?? null) : null;

            if ($problems = $this->problems($scheme, $kind, $data['bands'], $decimals, $resultMax)) {
                throw new ActionRefused(implode(' ', $problems));
            }

            $current = $scheme->activeBands($kind)->get();
            $flags = $current->filter(fn ($b) => $b->pending_question)
                ->mapWithKeys(fn ($b) => [mb_strtolower($b->label) => $b->pending_question]);

            $new = collect($data['bands'])
                ->map(fn ($band) => [
                    'label' => trim((string) $band['label']),
                    'min' => GradingBands::hundredths($band['min']),
                    'max' => GradingBands::hundredths($band['max']),
                    'value' => $kind === 'mark' && $scheme->usesBandValues() ? GradingBands::hundredths($band['value']) : null,
                    'is_fail' => filter_var($band['is_fail'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ])
                ->map(fn ($band) => $band + [
                    'pending_question' => empty($data['confirmed']) ? ($flags[mb_strtolower($band['label'])] ?? null) : null,
                ])
                ->sortByDesc('min')->values();

            $before = $current->map(fn (GradingSchemeBand $b) => [
                'label' => $b->label,
                'min' => GradingBands::hundredths($b->min),
                'max' => GradingBands::hundredths($b->max),
                'value' => $b->value === null ? null : GradingBands::hundredths($b->value),
                'is_fail' => $b->is_fail,
                'pending_question' => $b->pending_question,
            ])->values();

            $scheme->{$kind === 'mark' ? 'mark_decimals' : 'result_decimals'} = $decimals;
            if ($resultMax !== null) {
                $scheme->result_max = GradingBands::display(GradingBands::hundredths($resultMax));
            }
            $settingChanges = collect($scheme->getDirty())
                ->map(fn ($to, $field) => ['from' => $scheme->getOriginal($field), 'to' => $to])
                ->all();

            if ($settingChanges === [] && $before->all() === $new->all()) {
                throw new ActionRefused('Nothing was changed.');
            }

            $now = now();
            GradingSchemeBand::whereIn('id', $current->pluck('id'))->update(['archived_at' => $now]);
            GradingSchemeBand::insert($new->map(fn ($band) => [
                'grading_scheme_id' => $scheme->id,
                'kind' => $kind,
                'label' => $band['label'],
                'min' => GradingBands::display($band['min']),
                'max' => GradingBands::display($band['max']),
                'value' => $band['value'] === null ? null : GradingBands::display($band['value']),
                'is_fail' => $band['is_fail'],
                'pending_question' => $band['pending_question'],
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
            $scheme->save();

            $describe = fn ($bands) => $bands->map(fn ($b) => $b['label'].' '.GradingBands::display($b['min']).'-'.GradingBands::display($b['max'])
                .($b['value'] === null ? '' : ' = '.GradingBands::display($b['value']))
                .($b['is_fail'] ? ' (fail)' : '')
                .($b['pending_question'] ? ' ['.$b['pending_question'].']' : ''))->all();

            $this->audit->log($actor, 'Updated grading scheme bands', 'GradingScheme', $scheme->id, [
                'scheme' => $scheme->code,
                'set' => $kind,
                'settings' => $settingChanges,
                'before' => $describe($before),
                'after' => $describe($new),
            ]);
        });
    }

    /** Point a grade level at another scheme. This changes how the level's results are calculated. */
    public function assignLevel(GradeLevel $level, GradingScheme $scheme, User $actor): void
    {
        DB::transaction(function () use ($level, $scheme, $actor) {
            $level = GradeLevel::with('gradingScheme')->lockForUpdate()->findOrFail($level->id);
            $from = $level->gradingScheme;

            if ($from?->id === $scheme->id) {
                throw new ActionRefused("{$level->name} already uses {$scheme->name}.");
            }

            $level->update(['grading_scheme_id' => $scheme->id]);
            $this->audit->log($actor, 'Changed grade level grading scheme', 'GradeLevel', $level->id, [
                'grade_level' => $level->code,
                'from' => $from?->code,
                'to' => $scheme->code,
            ]);
        });
    }
}
