<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'id', 'staff_id_number', 'role_type', 'job_title', 'department_id', 'status', 'joined_date', 'phone',
    ];

    protected function casts(): array
    {
        return ['joined_date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function homeroomSections()
    {
        return $this->hasMany(Section::class, 'homeroom_teacher_id');
    }

    public function teachingAssignments()
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'staff_id');
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class, 'staff_id');
    }

    public function staffAttendances()
    {
        return $this->hasMany(StaffAttendance::class, 'staff_id');
    }

    public function importBatches()
    {
        return $this->hasMany(ImportBatch::class, 'uploaded_by');
    }

    public function acknowledgedAbsenceNotices()
    {
        return $this->hasMany(AbsenceNotice::class, 'acknowledged_by');
    }
}
