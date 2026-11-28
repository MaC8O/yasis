<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One subject in one grade level's curriculum (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3): its
 * report-card label and position, whether it is compulsory or part of a track (Grade 10 and Grades
 * 11-12: science or arts; `elective_slot` is kept for frozen results from before tracks), its credit
 * hours (Grades 11-12 GPA), grading mode and whether it counts in the result. Retired, never deleted.
 *
 * Built from a freeze's stored curriculum by fromSnapshot(), so frozen results recalculate with the
 * curriculum they were frozen with.
 */
class SubjectOffering extends Model
{
    public const GRADING_MODES = ['score' => 'Score (0-100)', 'pass_fail' => 'Pass/Fail', 'completion' => 'Pass / Incomplete / Complete (no mark)'];

    protected $fillable = [
        'grade_level_id', 'subject_id', 'report_label', 'is_compulsory', 'elective_slot', 'track', 'credit_hours',
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

    /** A row as a freeze stores it (see result_freezes.curriculum). */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id, 'subject_id' => $this->subject_id, 'report_label' => $this->report_label,
            'sort_order' => $this->sort_order, 'is_compulsory' => (bool) $this->is_compulsory, 'elective_slot' => $this->elective_slot,
            'track' => $this->track, 'credit_hours' => $this->credit_hours === null ? null : (string) $this->credit_hours,
            'grading_mode' => $this->grading_mode, 'counts_in_result' => (bool) $this->counts_in_result,
            'pending_question' => $this->pending_question,
        ];
    }

    /** An unsaved row rebuilt from a freeze's copy, with its subject loaded. */
    public static function fromSnapshot(int $gradeLevelId, array $row, ?Subject $subject): self
    {
        $offering = (new self)->forceFill(['grade_level_id' => $gradeLevelId] + $row);
        $offering->exists = true;

        return $offering->setRelation('subject', $subject);
    }

    /** Pass, Incomplete or Complete instead of a mark (SUPW). */
    public function usesCompletion(): bool
    {
        return $this->grading_mode === 'completion';
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
