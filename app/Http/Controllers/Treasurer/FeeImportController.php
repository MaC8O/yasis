<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Imports\FeeRecordsImport;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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

        $treasurer = $request->user()->staffProfile;

        $batch = ImportBatch::create([
            'uploaded_by' => $treasurer->id,
            'period' => $data['period'],
            'source_file' => $data['file']->getClientOriginalName(),
            'row_count' => $rows->count(),
            'uploaded_at' => now(),
        ]);

        $matched = 0;
        $unmatched = 0;

        foreach ($rows as $row) {
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

            ImportedFeeRecord::create([
                'import_batch_id' => $batch->id,
                'student_id' => $student?->id,
                'raw_student_key' => $student ? null : $externalKey,
                'txn_date' => $row['date'] ?? now()->toDateString(),
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
}
