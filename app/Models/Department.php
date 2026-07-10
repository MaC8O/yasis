<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'level'];

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
