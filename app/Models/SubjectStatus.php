<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SUPW's word for a student in a term (the `completion` grading mode, Grades 5-12): Pass,
 * Incomplete or Complete, entered by the subject's teacher. Complete counts as Pass; there is no
 * Fail. Incomplete can still become Complete after the term is locked.
 */
class SubjectStatus extends Model
{
    public const STATUSES = ['Pass', 'Incomplete', 'Complete'];

    protected $fillable = ['student_id', 'section_id', 'subject_id', 'term_id', 'status', 'recorded_by'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
