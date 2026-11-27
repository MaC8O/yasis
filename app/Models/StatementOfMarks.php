<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * §7.2: a Statement of Marks document request's details. Prepared by the Registrar with the two
 * dates; issued at its first print, when its content is saved with the year freeze it came from.
 */
class StatementOfMarks extends Model
{
    protected $table = 'statements_of_marks';

    protected $fillable = [
        'document_request_id', 'academic_year_id', 'section_id', 'grade_level_id', 'layout',
        'examination_date', 'issue_date', 'examination_centre', 'result_freeze_id', 'content',
        'issued_at', 'issued_by',
    ];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'content' => 'array', 'issued_at' => 'datetime'];
    }

    public function documentRequest()
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function isIssued(): bool
    {
        return $this->issued_at !== null;
    }
}
