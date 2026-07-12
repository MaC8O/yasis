<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ImportBatchController extends Controller
{
    public function index()
    {
        $batches = ImportBatch::withCount(['importedFeeRecords', 'importedFeeRecords as matched_count' => fn ($q) => $q->whereNotNull('student_id')])
            ->orderByDesc('uploaded_at')->get();

        return view('treasurer.history.index', [
            'batches' => $batches,
            'stats' => [
                'total' => $batches->count(),
                'published' => $batches->filter->is_published->count(),
                'needsReview' => $batches->filter(fn ($b) => ! $b->is_published)->count(),
            ],
        ]);
    }

    public function revert(Request $request, ImportBatch $importBatch, AuditService $audit)
    {
        $period = $importBatch->period;
        $id = $importBatch->id;

        $importBatch->delete();

        $audit->log($request->user(), 'Reverted fee import batch', 'ImportBatch', $id);

        return redirect()->route('treasurer.history.index')->with('status', "Batch {$period} reverted — all its records were removed.");
    }
}
