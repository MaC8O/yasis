<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads a teacher's bulk score sheet for a single assessment as raw rows keyed by header name.
 * Expected columns: student_id_number (required, used to match the roster), name (ignored — for
 * the teacher's readability), score (required). Rows are matched back to the assessment's section.
 */
class AssessmentScoresImport implements ToCollection, WithHeadingRow
{
    public \Illuminate\Support\Collection $rows;

    public function collection(\Illuminate\Support\Collection $rows): void
    {
        $this->rows = $rows;
    }
}
