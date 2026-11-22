<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One append-only movement of a student's behavior score. */
class BehaviorLedgerEntry extends Model
{
    public const DEDUCTION = 'Deduction';

    public const RESTORATION = 'Restoration';

    protected $fillable = [
        'student_id', 'academic_year_id', 'type', 'points_requested', 'points_applied', 'reason',
        'behavior_report_id', 'restores_entry_id', 'score_before', 'score_after', 'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'points_requested' => 'integer', 'points_applied' => 'integer',
            'score_before' => 'integer', 'score_after' => 'integer',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function report()
    {
        return $this->belongsTo(BehaviorReport::class, 'behavior_report_id');
    }

    public function actor()
    {
        return $this->belongsTo(StaffProfile::class, 'actor_id');
    }

    public function restores()
    {
        return $this->belongsTo(self::class, 'restores_entry_id');
    }

    public function restorations()
    {
        return $this->hasMany(self::class, 'restores_entry_id');
    }

    public function isDeduction(): bool
    {
        return $this->type === self::DEDUCTION;
    }

    /** Points of this deduction not yet given back by restorations. */
    public function restorablePoints(): int
    {
        return max(0, $this->points_applied - (int) $this->restorations()->sum('points_applied'));
    }
}
