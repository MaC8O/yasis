<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the Registrar's bulk student-registration Excel/CSV as raw rows keyed by header name.
 * Expected columns: student_id_number, name, department (required),
 * date_of_birth, gender, religious_background, admission_date, section,
 * guardian_name, guardian_email, guardian_relationship, guardian_phone (all optional).
 */
class StudentsImport implements ToCollection, WithHeadingRow
{
    public \Illuminate\Support\Collection $rows;

    public function collection(\Illuminate\Support\Collection $rows): void
    {
        $this->rows = $rows;
    }
}
