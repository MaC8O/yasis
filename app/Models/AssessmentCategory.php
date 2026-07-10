<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentCategory extends Model
{
    protected $fillable = ['section_id', 'subject_id', 'term_id', 'name', 'weight_pct'];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class, 'category_id');
    }
}
