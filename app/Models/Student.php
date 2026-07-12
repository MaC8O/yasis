<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id', 'student_id_number', 'name', 'photo_path',
        'date_of_birth', 'gender', 'religious_background', 'admission_date', 'department_id', 'enrollment_status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'admission_date' => 'date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function importedFeeRecords()
    {
        return $this->hasMany(ImportedFeeRecord::class);
    }

    public function absenceNotices()
    {
        return $this->hasMany(AbsenceNotice::class);
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function guardiansList(): string
    {
        return $this->guardians->pluck('user.name')->filter()->implode(', ');
    }
}
