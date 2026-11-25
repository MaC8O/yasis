<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A student's chosen subject in one elective slot for one academic year (Grades 11-12). */
class StudentElective extends Model
{
    protected $fillable = ['student_id', 'academic_year_id', 'elective_slot', 'subject_offering_id'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function offering()
    {
        return $this->belongsTo(SubjectOffering::class, 'subject_offering_id');
    }
}
