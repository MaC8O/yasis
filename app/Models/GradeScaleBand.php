<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeScaleBand extends Model
{
    protected $fillable = ['department_id', 'letter', 'min_score', 'gpa_point'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
