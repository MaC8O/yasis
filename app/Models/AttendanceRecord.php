<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'student_id', 'section_id', 'term_id', 'attendance_date',
        'status', 'remark', 'absence_notice_id', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['attendance_date' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function absenceNotice()
    {
        return $this->belongsTo(AbsenceNotice::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(StaffProfile::class, 'recorded_by');
    }
}
