<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the Treasurer's Excel/CSV export as raw rows keyed by header name.
 * Expected columns: student_id, date, amount, balance, and optionally
 * status (Owed/Paid/Partial/Outstanding) and restricted (true/false — SDA discounts/allowances).
 * The exact matching key is an open item per §3.6 pending the school's sample export;
 * this implementation matches on the ISMS student_id_number as the agreed default.
 */
class FeeRecordsImport implements ToCollection, WithHeadingRow
{
    public \Illuminate\Support\Collection $rows;

    public function collection(\Illuminate\Support\Collection $rows): void
    {
        $this->rows = $rows;
    }
}
