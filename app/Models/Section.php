<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = ['academic_year_id', 'department_id', 'grade_level_id', 'name', 'homeroom_teacher_id', 'capacity', 'archived_at'];

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

    /**
     * School order: by grade level (KG1, KG2, 1 … 12), sections without a level last, then by
     * name so "Grade 9-A" comes before "Grade 9-B".
     */
    public function scopeOrderByGradeLevel($query)
    {
        $level = '(SELECT grade_levels.sort_order FROM grade_levels WHERE grade_levels.id = sections.grade_level_id)';

        return $query->orderByRaw("{$level} IS NULL")->orderByRaw($level)->orderByRaw('LENGTH(sections.name), sections.name');
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
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
