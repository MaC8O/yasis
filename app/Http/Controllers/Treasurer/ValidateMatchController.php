<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ValidateMatchController extends Controller
{
    public function index(Request $request)
    {
        $batches = ImportBatch::orderByDesc('uploaded_at')->get();
        $batch = $batches->firstWhere('id', $request->integer('batch')) ?? $batches->first();

        $records = $batch ? ImportedFeeRecord::where('import_batch_id', $batch->id)->with('student')->get() : collect();

        return view('treasurer.validate.index', [
            'batches' => $batches,
            'batch' => $batch,
            'uploaded' => $records->count(),
            'matched' => $records->whereNotNull('student_id')->count(),
            'unmatchedRecords' => $records->whereNull('student_id'),
            'restrictedCount' => $records->where('is_restricted', true)->count(),
        ]);
    }

    public function resolve(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'exists:students,student_id_number'],
        ]);

        $student = Student::where('student_id_number', $data['student_id_number'])->firstOrFail();
        $importedFeeRecord->update(['student_id' => $student->id, 'raw_student_key' => null]);

        $audit->log($request->user(), 'Resolved unmatched fee row', 'ImportedFeeRecord', $importedFeeRecord->id);

        return back()->with('status', "Row mapped to {$student->first_name} {$student->last_name}.");
    }

    public function publish(Request $request, ImportBatch $importBatch, AuditService $audit)
    {
        $importBatch->update(['published_at' => now()]);
        $audit->log($request->user(), 'Published fee import batch', 'ImportBatch', $importBatch->id);

        return back()->with('status', "Batch {$importBatch->period} published. Matched records are now visible to leadership and guardians.");
    }
}
