<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportedFeeRecord;
use App\Services\FeeSummaryService;

class TreasurerDashboardController extends Controller
{
    public function index(FeeSummaryService $service)
    {
        $total = ImportedFeeRecord::count();
        $matched = ImportedFeeRecord::whereNotNull('student_id')->count();

        return view('treasurer.dashboard', [
            'totalRows' => $total,
            'matchedRows' => $matched,
            'needsReview' => ImportedFeeRecord::unmatched()->count(),
            'recentBatches' => ImportBatch::withCount(['importedFeeRecords', 'importedFeeRecords as matched_count' => fn ($q) => $q->whereNotNull('student_id')])
                ->orderByDesc('uploaded_at')->take(5)->get(),
            'byPeriod' => $service->collectionRateByPeriod(),
        ]);
    }
}
