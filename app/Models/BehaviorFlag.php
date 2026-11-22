<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BehaviorFlag extends Model
{
    /** Stages from least to most serious. */
    public const STAGES = ['Warning', 'FinalWarning', 'ExpulsionReview'];

    public const LABELS = ['Warning' => 'Warning', 'FinalWarning' => 'Final warning', 'ExpulsionReview' => 'Expulsion review'];

    protected $fillable = [
        'student_id', 'academic_year_id', 'stage', 'threshold_at_open', 'score_at_open',
        'opened_by_entry_id', 'opened_at', 'closed_at', 'closed_by_entry_id', 'close_reason',
    ];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime', 'threshold_at_open' => 'integer', 'score_at_open' => 'integer'];
    }

    public static function label(?string $stage): ?string
    {
        return $stage ? self::LABELS[$stage] : null;
    }

    public static function rank(string $stage): int
    {
        return (int) array_search($stage, self::STAGES, true);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
