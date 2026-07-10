<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'staff_id', 'leave_type_id', 'from_date', 'to_date', 'days',
        'reason', 'status', 'submitted_by', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'decided_at' => 'datetime'];
    }

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'submitted_by');
    }

    public function decidedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'decided_by');
    }

    public function staffAttendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }
}
