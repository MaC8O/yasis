<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The stored results of a locked term (or, with no term, of the year), calculated once when the
 * Principal locked it, with a copy of the grading rules used. Lifted, never deleted.
 */
class ResultFreeze extends Model
{
    /** On a relock: the rules of the term's previous freeze (its first, unless it was recalculated since). */
    public const RULES_PREVIOUS = 'previous_freeze';

    public const RULES_CURRENT = 'current';

    protected $fillable = [
        'academic_year_id', 'term_id', 'rules', 'reason', 'curriculum', 'attendance_rules', 'frozen_at', 'frozen_by', 'lifted_at', 'lifted_by',
    ];

    protected function casts(): array
    {
        return ['frozen_at' => 'datetime', 'lifted_at' => 'datetime', 'curriculum' => 'array', 'attendance_rules' => 'array'];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('lifted_at');
    }

    public function isYear(): bool
    {
        return $this->term_id === null;
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function frozenBy()
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    public function schemes()
    {
        return $this->hasMany(ResultFreezeScheme::class);
    }

    public function studentResults()
    {
        return $this->hasMany(StudentResult::class);
    }
}
