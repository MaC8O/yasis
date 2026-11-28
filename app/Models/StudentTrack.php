<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A student's track for one school year (Grade 10, Grades 11-12): science or arts, set by the
 * Registrar. The student takes the compulsory subjects plus their track's. `set_by` is null only
 * for a track moved from the student's old elective choices.
 */
class StudentTrack extends Model
{
    protected $fillable = ['student_id', 'academic_year_id', 'track', 'set_by'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public static function label(?string $track): string
    {
        return $track === null ? 'No track' : ucfirst(str_replace('_', ' ', $track));
    }
}
