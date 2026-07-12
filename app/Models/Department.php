<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'level'];

    /** The levels that hold students/classes, as opposed to Administrative (staff) units. */
    public const ACADEMIC_LEVELS = ['Early Years', 'Primary', 'Secondary'];

    /** Academic departments only — the ones a student can be enrolled in. */
    public function scopeAcademic($query)
    {
        return $query->whereIn('level', self::ACADEMIC_LEVELS);
    }

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function gradeScaleBands()
    {
        return $this->hasMany(GradeScaleBand::class);
    }
}
