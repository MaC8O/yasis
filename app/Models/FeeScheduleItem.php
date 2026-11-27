<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One grade level's fee of one type in a schedule: the yearly fee (one-time), tuition (the year's
 * amount, paid in instalments) or the refundable deposit (one-time, new students only).
 */
class FeeScheduleItem extends Model
{
    public const TYPES = [
        'yearly' => 'Yearly fee',
        'tuition' => 'Tuition',
        'deposit' => 'Refundable deposit',
    ];

    protected $fillable = ['fee_schedule_id', 'grade_level_id', 'fee_type', 'amount', 'instalments', 'new_students_only', 'pending_question'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'new_students_only' => 'boolean'];
    }

    public function schedule()
    {
        return $this->belongsTo(FeeSchedule::class, 'fee_schedule_id');
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }
}
