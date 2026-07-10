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
            'records' => $records,
            'uploaded' => $records->count(),
            'matched' => $records->whereNotNull('student_id')->count(),
            'unmatchedRecords' => $records->whereNull('student_id')->where('is_held', false),
            'restrictedCount' => $records->where('is_restricted', true)->count(),
            'heldCount' => $records->where('is_held', true)->count(),
            'blockingCount' => $records->whereNull('student_id')->where('is_held', false)->count(),
        ]);
    }

    /** §9.4: restrict marks a row SDA-sensitive — hidden from guardians/students everywhere. */
    public function toggleRestrict(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $importedFeeRecord->update(['is_restricted' => ! $importedFeeRecord->is_restricted]);

        $audit->log(
            $request->user(),
            $importedFeeRecord->is_restricted ? 'Restricted fee row' : 'Unrestricted fee row',
            'ImportedFeeRecord',
            $importedFeeRecord->id
        );

        return back()->with('status', $importedFeeRecord->is_restricted
            ? 'Row restricted — hidden from guardians and students.'
            : 'Restriction removed — row follows normal visibility.');
    }

    /** §9.4: hold parks a row — it stops blocking publish and never reaches family views. */
    public function toggleHold(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $importedFeeRecord->update(['is_held' => ! $importedFeeRecord->is_held]);

        $audit->log(
            $request->user(),
            $importedFeeRecord->is_held ? 'Held fee row' : 'Released held fee row',
            'ImportedFeeRecord',
            $importedFeeRecord->id
        );

        return back()->with('status', $importedFeeRecord->is_held
            ? 'Row held — parked out of publishing until released.'
            : 'Row released from hold.');
    }

    public function resolve(Request $request, ImportedFeeRecord $importedFeeRecord, AuditService $audit)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'exists:students,student_id_number'],
        ]);

        $student = Student::where('student_id_number', $data['student_id_number'])->firstOrFail();
        $importedFeeRecord->update(['student_id' => $student->id, 'raw_student_key' => null]);

        $audit->log($request->user(), 'Resolved unmatched fee row', 'ImportedFeeRecord', $importedFeeRecord->id);

        return back()->with('status', "Row mapped to {$student->name}.");
    }

    public function publish(Request $request, ImportBatch $importBatch, AuditService $audit)
    {
        // §9.4: cannot publish while unresolved unmatched rows remain — hold a row to park it.
        $blocking = $importBatch->importedFeeRecords()
            ->whereNull('student_id')->where('is_held', false)->count();

        if ($blocking > 0) {
            return back()->withErrors([
                'publish' => "Cannot publish: {$blocking} unmatched row(s) remain. Match them to a student or put them on hold first.",
            ]);
        }

        $held = $importBatch->importedFeeRecords()->where('is_held', true)->count();

        $importBatch->update(['published_at' => now()]);
        $audit->log($request->user(), 'Published fee import batch', 'ImportBatch', $importBatch->id);

        $note = $held > 0 ? " Published with {$held} held row(s) excluded." : '';

        return back()->with('status', "Batch {$importBatch->period} published. Matched records are now visible to leadership and guardians.{$note}");
    }
}
