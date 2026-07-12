<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportCardComment extends Model
{
    protected $fillable = ['student_id', 'term_id', 'staff_id', 'comment'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }
}
