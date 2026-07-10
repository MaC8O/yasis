<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $fillable = ['user_id', 'relationship', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function absenceNotices()
    {
        return $this->hasMany(AbsenceNotice::class);
    }
}
