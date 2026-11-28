<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A grading scheme (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4-6): its mark bands, its
 * class-of-pass bands, the rounding rule applied before each band lookup, and whether one failed
 * subject fails the year. Grade levels point at a scheme. `code` and `result_basis` never change.
 */
class GradingScheme extends Model
{
    public const RESULT_BASES = [
        'average' => 'Average of the counted subjects',
        'point_total' => 'Point total of the counted subjects',
        'gpa' => 'Credit-hour GPA',
    ];

    public const ROUNDING_MODES = [
        'half_up' => 'Half up (60.5 becomes 61)',
        'down' => 'Down (60.5 stays 60)',
    ];

    public const FAIL_RULES = [
        'any_counted' => 'A counted subject below the pass mark fails the year',
        'any_compulsory' => 'A compulsory subject below the pass mark fails the year',
        'none' => 'A failed subject does not fail the year on its own',
    ];

    /** Q20: where students who failed are placed when ranking for position in class. */
    public const FAILED_RANKINGS = [
        'together' => 'Everyone is ranked together by their result',
        'failed_last' => 'Students who failed are ranked after all who passed',
    ];

    public const BAND_KINDS = ['mark' => 'Subject marks', 'result' => 'Class of pass'];

    protected $fillable = [
        'code', 'name', 'result_basis', 'result_max', 'mark_decimals', 'result_decimals',
        'rounding_mode', 'subject_fail_rule', 'failed_ranking', 'tie_ranking', 'rank_basis', 'uses_gradebook_template', 'pending_question',
    ];

    protected function casts(): array
    {
        return [
            'result_max' => 'decimal:2',
            'mark_decimals' => 'integer',
            'result_decimals' => 'integer',
            'uses_gradebook_template' => 'boolean',
        ];
    }

    public function bands()
    {
        return $this->hasMany(GradingSchemeBand::class);
    }

    /** The bands of one set (`mark` or `result`) in force now, highest first. */
    public function activeBands(string $kind)
    {
        return $this->bands()->active()->where('kind', $kind)->orderByDesc('min');
    }

    public function gradeLevels()
    {
        return $this->hasMany(GradeLevel::class);
    }

    /** Grade 10 bands carry points and Grade 11-12 bands a grade value; KG-9 marks carry none. */
    public function usesBandValues(): bool
    {
        return $this->result_basis !== 'average';
    }

    /** The average's range is always 0-100; the point total's and the GPA's top is set per scheme. */
    public function hasEditableResultMax(): bool
    {
        return $this->result_basis !== 'average';
    }

    public function decimals(string $kind): int
    {
        return $kind === 'mark' ? $this->mark_decimals : $this->result_decimals;
    }

    /** The top of a band set's range; every set starts at 0. */
    public function rangeMax(string $kind): string
    {
        return $kind === 'mark' ? '100.00' : (string) $this->result_max;
    }

    public function resultLabel(): string
    {
        return ['average' => 'Average', 'point_total' => 'Point total', 'gpa' => 'GPA'][$this->result_basis];
    }

    public function valueLabel(): ?string
    {
        return ['average' => null, 'point_total' => 'Points', 'gpa' => 'Grade value'][$this->result_basis];
    }

    /** The rules-file questions this scheme's settings are waiting on, e.g. ['Q10', 'Q19']. */
    public function pendingQuestions(): array
    {
        return $this->pending_question ? array_values(array_filter(array_map('trim', explode(',', $this->pending_question)))) : [];
    }
}
