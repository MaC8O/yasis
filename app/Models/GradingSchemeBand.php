<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One band of a grading scheme: a `mark` band (subject mark to letter, with points or a grade
 * value for Grade 10 and 11-12) or a `result` band (average, point total or GPA to class of pass).
 * The range is inclusive. A band set is replaced as a whole: the old rows are archived, never
 * deleted, so the set in force at any past moment can be found with inForceAt().
 */
class GradingSchemeBand extends Model
{
    protected $fillable = [
        'grading_scheme_id', 'kind', 'label', 'min', 'max', 'value', 'is_fail', 'pending_question',
        'archived_at', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'min' => 'decimal:2',
            'max' => 'decimal:2',
            'value' => 'decimal:2',
            'is_fail' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function scheme()
    {
        return $this->belongsTo(GradingScheme::class, 'grading_scheme_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    /** The bands that were in force at $at (for example when a term was locked). */
    public function scopeInForceAt($query, DateTimeInterface $at)
    {
        return $query->where('created_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhere('archived_at', '>', $at));
    }

    public function pendingQuestions(): array
    {
        return $this->pending_question ? array_values(array_filter(array_map('trim', explode(',', $this->pending_question)))) : [];
    }
}
