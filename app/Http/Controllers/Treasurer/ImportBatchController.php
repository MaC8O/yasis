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
        // Counts come from the rows that exist now — row_count is only what the file held at import.
        $batches = ImportBatch::with('uploadedBy.user')
            ->withCount([
                'importedFeeRecords',
                'importedFeeRecords as matched_count' => fn ($q) => $q->whereNotNull('student_id'),
                'importedFeeRecords as unmatched_count' => fn ($q) => $q->whereNull('student_id')->where('is_held', false),
                'importedFeeRecords as held_count' => fn ($q) => $q->where('is_held', true),
                'importedFeeRecords as restricted_count' => fn ($q) => $q->where('is_restricted', true),
            ])
            ->withSum('importedFeeRecords as charged_sum', 'amount')
            ->withSum('importedFeeRecords as owed_sum', 'balance')
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
        $rows = $importBatch->importedFeeRecords()->count();

        $importBatch->delete();

        $audit->log($request->user(), 'Reverted fee import batch', 'ImportBatch', $id, ['period' => $period, 'rows_removed' => $rows]);

        return redirect()->route('treasurer.history.index')
            ->with('status', "Batch {$period} reverted — its {$rows} ".str('row')->plural($rows).' were removed.');
    }
}
