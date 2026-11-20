<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Imports\FeeRecordsImport;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class FeeImportController extends Controller
{
    protected array $validStatuses = ['Owed', 'Paid', 'Partial', 'Outstanding'];

    public function index()
    {
        return view('treasurer.import.index');
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $import = new FeeRecordsImport;
        Excel::import($import, $request->file('file'));
        $rows = $import->rows;

        // Read every date first: a file re-saved in Excel carries dd/mm/yyyy text or Excel date
        // numbers, and an unreadable date must reject the file rather than error or store 1970-01-01.
        $dates = [];
        $badRows = [];
        foreach ($rows as $i => $row) {
            $dates[$i] = $this->parseDate($row['date'] ?? null);
            if ($dates[$i] === null) {
                $badRows[] = $i + 2; // +1 for the header row, +1 for 1-based numbering
            }
        }
        if ($badRows) {
            return back()->withErrors([
                'file' => 'Nothing was imported: unreadable date on row(s) '.implode(', ', array_slice($badRows, 0, 10))
                    .(count($badRows) > 10 ? ' and '.(count($badRows) - 10).' more' : '')
                    .'. Use YYYY-MM-DD or DD/MM/YYYY.',
            ]);
        }

        $treasurer = $request->user()->staffProfile;

        return DB::transaction(fn () => $this->storeBatch($request, $audit, $data, $rows, $dates, $treasurer));
    }

    protected function storeBatch(Request $request, AuditService $audit, array $data, $rows, array $dates, $treasurer)
    {
        $batch = ImportBatch::create([
            'uploaded_by' => $treasurer->id,
            'period' => $data['period'],
            'source_file' => $data['file']->getClientOriginalName(),
            'row_count' => $rows->count(),
            'uploaded_at' => now(),
        ]);

        $matched = 0;
        $unmatched = 0;

        foreach ($rows as $i => $row) {
            $externalKey = trim((string) ($row['student_id'] ?? ''));
            $student = $externalKey !== '' ? Student::where('student_id_number', $externalKey)->first() : null;

            $amount = (float) ($row['amount'] ?? 0);
            $balance = (float) ($row['balance'] ?? 0);

            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $matchedStatus = collect($this->validStatuses)->first(fn ($s) => strtolower($s) === $status);
            if (! $matchedStatus) {
                $matchedStatus = $balance <= 0 ? 'Paid' : ($balance < $amount ? 'Partial' : 'Owed');
            }

            $restrictedRaw = strtolower(trim((string) ($row['restricted'] ?? '')));
            $isRestricted = in_array($restrictedRaw, ['1', 'true', 'yes', 'sda'], true);

            $name = trim((string) ($row['student_name'] ?? $row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));

            ImportedFeeRecord::create([
                'import_batch_id' => $batch->id,
                'student_id' => $student?->id,
                'raw_student_key' => $student ? null : $externalKey,
                'raw_student_name' => $name !== '' ? mb_substr($name, 0, 150) : null,
                'txn_date' => $dates[$i],
                'description' => $description !== '' ? mb_substr($description, 0, 150) : null,
                'amount' => $amount,
                'balance' => $balance,
                'status' => $matchedStatus,
                'is_restricted' => $isRestricted,
            ]);

            $student ? $matched++ : $unmatched++;
        }

        $audit->log($request->user(), 'Imported fee records', 'ImportBatch', $batch->id);

        return redirect()->route('treasurer.validate.index', ['batch' => $batch->id])
            ->with('status', "Imported {$rows->count()} rows — {$matched} matched, {$unmatched} unmatched.");
    }

    /** Y-m-d for a blank (today), Excel date number, or ISO / day-first date; null if unreadable. */
    protected function parseDate($raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return now()->toDateString();
        }

        if (is_numeric($raw)) {
            $serial = (float) $raw;

            return $serial >= 1 && $serial < 2958466
                ? ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d')
                : null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $raw);
            $errors = \DateTime::getLastErrors();
            if ($date && ! ($errors && ($errors['warning_count'] || $errors['error_count']))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
