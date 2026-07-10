<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the Admin's bulk user-account Excel/CSV as raw rows keyed by header name.
 * Expected columns: name, email, role (required); staff_id_number, department,
 * joined_date, date_of_birth, gender, phone, address (optional; staff roles
 * require staff_id_number).
 */
class UsersImport implements ToCollection, WithHeadingRow
{
    public \Illuminate\Support\Collection $rows;

    public function collection(\Illuminate\Support\Collection $rows): void
    {
        $this->rows = $rows;
    }
}
