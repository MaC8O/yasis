<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A student's stored result for a freeze. Rows replaced by a grade change on a locked term keep
 * `superseded_at`; readers use the current ones.
 */
class StudentResult extends Model
{
    protected $fillable = [
        'result_freeze_id', 'student_id', 'section_id', 'grade_level_id', 'grading_scheme_id', 'result_basis',
        'status', 'status_reason', 'result_value', 'result_unrounded', 'class_label', 'passed', 'position',
        'ranked_count', 'recommendation', 'superseded_at', 'superseded_reason',
    ];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'position' => 'integer',
            'ranked_count' => 'integer',
            'superseded_at' => 'datetime',
        ];
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('superseded_at');
    }

    public function freeze()
    {
        return $this->belongsTo(ResultFreeze::class, 'result_freeze_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subjects()
    {
        return $this->hasMany(StudentSubjectResult::class)->orderBy('sort_order');
    }
}
