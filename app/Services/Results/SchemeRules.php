<?php

namespace App\Services\Results;

use App\Models\GradingScheme;
use App\Models\ResultFreezeScheme;
use App\Support\GradingBands;

/**
 * The grading rules a calculation uses: one scheme's settings and its two band sets, either as
 * they are now (fromScheme) or as a freeze stored them (fromSnapshot). Numbers are decimal strings.
 * Each band: label, min, max, value (or null), is_fail, pending_question.
 */
final class SchemeRules
{
    /**
     * @param  list<array{label: string, min: string, max: string, value: ?string, is_fail: bool, pending_question: ?string}>  $markBands
     * @param  list<array{label: string, min: string, max: string, value: ?string, is_fail: bool, pending_question: ?string}>  $resultBands
     */
    public function __construct(
        public readonly int $schemeId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $resultBasis,
        public readonly string $resultMax,
        public readonly int $markDecimals,
        public readonly int $resultDecimals,
        public readonly string $roundingMode,
        public readonly string $subjectFailRule,
        public readonly ?string $pendingQuestion,
        public readonly array $markBands,
        public readonly array $resultBands,
        public readonly string $failedRanking = 'together',
    ) {}

    public static function fromScheme(GradingScheme $scheme): self
    {
        $bands = fn (string $kind) => $scheme->activeBands($kind)->get()->map(fn ($b) => [
            'label' => $b->label,
            'min' => (string) $b->min,
            'max' => (string) $b->max,
            'value' => $b->value === null ? null : (string) $b->value,
            'is_fail' => (bool) $b->is_fail,
            'pending_question' => $b->pending_question,
        ])->values()->all();

        return new self(
            $scheme->id, $scheme->code, $scheme->name, $scheme->result_basis, (string) $scheme->result_max,
            $scheme->mark_decimals, $scheme->result_decimals, $scheme->rounding_mode, $scheme->subject_fail_rule,
            $scheme->pending_question, $bands('mark'), $bands('result'), $scheme->failed_ranking ?? 'together',
        );
    }

    public static function fromSnapshot(ResultFreezeScheme $snapshot): self
    {
        $s = $snapshot->settings;

        return new self(
            $snapshot->grading_scheme_id, $s['code'], $s['name'], $s['result_basis'], $s['result_max'],
            (int) $s['mark_decimals'], (int) $s['result_decimals'], $s['rounding_mode'], $s['subject_fail_rule'],
            $s['pending_question'], $snapshot->mark_bands, $snapshot->result_bands,
            // Freezes made before Q20 existed ranked everyone together.
            $s['failed_ranking'] ?? 'together',
        );
    }

    /** @return array{settings: array, mark_bands: array, result_bands: array} */
    public function toSnapshot(): array
    {
        return [
            'settings' => [
                'code' => $this->code,
                'name' => $this->name,
                'result_basis' => $this->resultBasis,
                'result_max' => $this->resultMax,
                'mark_decimals' => $this->markDecimals,
                'result_decimals' => $this->resultDecimals,
                'rounding_mode' => $this->roundingMode,
                'subject_fail_rule' => $this->subjectFailRule,
                'failed_ranking' => $this->failedRanking,
                'pending_question' => $this->pendingQuestion,
            ],
            'mark_bands' => $this->markBands,
            'result_bands' => $this->resultBands,
        ];
    }

    /** The rules-file questions flagged on the scheme or any of its bands, e.g. ['Q10', 'Q19']. */
    public function pendingQuestions(): array
    {
        $flags = collect([$this->pendingQuestion])
            ->merge(array_column($this->markBands, 'pending_question'))
            ->merge(array_column($this->resultBands, 'pending_question'))
            ->filter()
            ->flatMap(fn ($q) => array_map('trim', explode(',', $q)));

        return $flags->filter()->unique()->sort(SORT_NATURAL)->values()->all();
    }

    /** A readable summary for the audit log: settings and each band as "First 81-94" or "A 85-100 = 10 (fail)". */
    public function describe(): array
    {
        $band = fn (array $b) => $b['label'].' '.GradingBands::display(GradingBands::hundredths($b['min'])).'-'.GradingBands::display(GradingBands::hundredths($b['max']))
            .($b['value'] === null ? '' : ' = '.GradingBands::display(GradingBands::hundredths($b['value'])))
            .($b['is_fail'] ? ' (fail)' : '');

        return [
            'settings' => array_diff_key($this->toSnapshot()['settings'], ['code' => true, 'name' => true]),
            'mark' => array_map($band, $this->markBands),
            'result' => array_map($band, $this->resultBands),
        ];
    }

    public function resultLabel(): string
    {
        return ['average' => 'Average', 'point_total' => 'Point total', 'gpa' => 'GPA'][$this->resultBasis];
    }

    public function usesBandValues(): bool
    {
        return $this->resultBasis !== 'average';
    }
}
