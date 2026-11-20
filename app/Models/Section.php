<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = ['academic_year_id', 'department_id', 'name', 'homeroom_teacher_id', 'capacity', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** Sections still in use (not archived). */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** The grade number in the section name ("Grade 9-A" → 9), or null for Nursery/KG-style names. */
    public function gradeNumber(): ?int
    {
        return preg_match('/\bgrade\s*(\d{1,2})\b/i', $this->name, $m) ? (int) $m[1] : null;
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function homeroomTeacher()
    {
        return $this->belongsTo(StaffProfile::class, 'homeroom_teacher_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function teachingAssignments()
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function assessmentCategories()
    {
        return $this->hasMany(AssessmentCategory::class);
    }
}
