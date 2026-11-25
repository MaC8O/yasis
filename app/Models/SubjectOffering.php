<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One subject in one grade level's curriculum (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3): its
 * report-card label and position, whether it is compulsory or an elective-slot choice, its credit
 * hours (Grades 11-12 GPA), grading mode and whether it counts in the result. Retired, never deleted.
 */
class SubjectOffering extends Model
{
    public const GRADING_MODES = ['score' => 'Score (0-100)', 'pass_fail' => 'Pass/Fail'];

    protected $fillable = [
        'grade_level_id', 'subject_id', 'report_label', 'is_compulsory', 'elective_slot', 'credit_hours',
        'grading_mode', 'counts_in_result', 'sort_order', 'pending_question', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_compulsory' => 'boolean',
            'counts_in_result' => 'boolean',
            'credit_hours' => 'decimal:1',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** The rules-file questions this row's values are waiting on, e.g. ['Q5', 'Q6']. */
    public function pendingQuestions(): array
    {
        return $this->pending_question ? array_values(array_filter(array_map('trim', explode(',', $this->pending_question)))) : [];
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function electiveChoices()
    {
        return $this->hasMany(StudentElective::class);
    }
}
