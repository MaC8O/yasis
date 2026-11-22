<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A teacher's lesson plan for one class (section + subject) and term. Every upload is a new
 * version; versions are kept for as long as the plan exists (the reviews point at them).
 * Submitted ("Awaiting review") → Approved | NeedsChanges by the VP; a new version returns the
 * plan to Submitted. Deletable by its teacher only while it has no review.
 */
class LessonPlan extends Model
{
    public const SUBMITTED = 'Submitted';

    public const APPROVED = 'Approved';

    public const NEEDS_CHANGES = 'NeedsChanges';

    public const STATUSES = [self::SUBMITTED, self::APPROVED, self::NEEDS_CHANGES];

    public const STATUS_LABELS = [
        self::SUBMITTED => 'Awaiting review',
        self::APPROVED => 'Approved',
        self::NEEDS_CHANGES => 'Needs changes',
    ];

    /** Largest single file, in kilobytes (the validation rule's unit). */
    public const MAX_FILE_KB = 10240;

    /** Total stored size of all of one plan's versions. Past it, the teacher starts a new plan. */
    public const MAX_TOTAL_BYTES = 50 * 1024 * 1024;

    protected $fillable = [
        'teacher_id', 'section_id', 'subject_id', 'term_id', 'title', 'note', 'status',
        'current_version', 'submitted_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'current_version' => 'integer'];
    }

    public function teacher()
    {
        return $this->belongsTo(StaffProfile::class, 'teacher_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function versions()
    {
        return $this->hasMany(LessonPlanVersion::class)->orderByDesc('version');
    }

    public function currentVersion()
    {
        return $this->hasOne(LessonPlanVersion::class)->ofMany('version', 'max');
    }

    public function reviews()
    {
        return $this->hasMany(LessonPlanReview::class)->orderByDesc('id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::APPROVED => 'green',
            self::NEEDS_CHANGES => 'pink',
            default => 'yellow',
        };
    }
}
