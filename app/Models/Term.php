<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $fillable = ['academic_year_id', 'name', 'sequence', 'start_date', 'end_date', 'is_locked', 'results_released'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_locked' => 'boolean', 'results_released' => 'boolean'];
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
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
