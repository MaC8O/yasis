<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An incident report. Submitted → Dismissed | Deducted by the VP, or Voided by its author while
 * still Submitted. Never deleted; earlier versions are kept in behavior_report_versions.
 */
class BehaviorReport extends Model
{
    public const SUBMITTED = 'Submitted';

    public const DISMISSED = 'Dismissed';

    public const DEDUCTED = 'Deducted';

    public const VOIDED = 'Voided';

    /** Statuses whose office referral counts as an office visit. Voided ones never count. */
    public const COUNTED_STATUSES = [self::SUBMITTED, self::DISMISSED, self::DEDUCTED];

    protected $fillable = [
        'student_id', 'academic_year_id', 'reported_by', 'source', 'incident_date', 'behavior_category_id',
        'description', 'is_office_referral', 'status', 'reviewed_by', 'reviewed_at', 'dismiss_reason',
        'voided_by', 'voided_at', 'void_reason', 'version', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'is_office_referral' => 'boolean',
            'reviewed_at' => 'datetime',
            'voided_at' => 'datetime',
            'edited_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function category()
    {
        return $this->belongsTo(BehaviorCategory::class, 'behavior_category_id');
    }

    public function reporter()
    {
        return $this->belongsTo(StaffProfile::class, 'reported_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(StaffProfile::class, 'reviewed_by');
    }

    public function voider()
    {
        return $this->belongsTo(StaffProfile::class, 'voided_by');
    }

    public function versions()
    {
        return $this->hasMany(BehaviorReportVersion::class)->orderByDesc('version');
    }

    public function deduction()
    {
        return $this->hasOne(BehaviorLedgerEntry::class)->where('type', BehaviorLedgerEntry::DEDUCTION);
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::SUBMITTED;
    }

    /** The status label everyone sees, students and guardians included. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::SUBMITTED => 'Awaiting review',
            self::DISMISSED => 'Reviewed — no deduction',
            self::DEDUCTED => 'Points deducted',
            self::VOIDED => 'Withdrawn',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::SUBMITTED => 'yellow',
            self::DISMISSED => 'blue',
            self::DEDUCTED => 'pink',
            self::VOIDED => 'neutral',
        };
    }
}
