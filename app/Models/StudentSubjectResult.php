<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One subject of a stored result, with a copy of its curriculum row at freeze time. */
class StudentSubjectResult extends Model
{
    protected $fillable = [
        'student_result_id', 'subject_id', 'subject_offering_id', 'report_label', 'sort_order', 'in_curriculum',
        'is_compulsory', 'elective_slot', 'track', 'taken', 'counts_in_result', 'grading_mode', 'credit_hours', 'mark',
        'rounded_mark', 'band_label', 'band_value', 'is_fail', 'pending_question',
    ];

    protected function casts(): array
    {
        return [
            'in_curriculum' => 'boolean',
            'is_compulsory' => 'boolean',
            'taken' => 'boolean',
            'counts_in_result' => 'boolean',
            'is_fail' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
