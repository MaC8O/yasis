<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceNotice extends Model
{
    protected $fillable = [
        'student_id', 'guardian_id', 'from_date', 'to_date', 'reason',
        'status', 'acknowledged_by', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'acknowledged_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'acknowledged_by');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
