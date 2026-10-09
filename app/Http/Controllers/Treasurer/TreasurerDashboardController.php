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
        $summaries = $service->studentSummaries();
        $billed = $summaries->sum('total_billed');

        return view('treasurer.dashboard', [
            'receivables' => $summaries->sum('balance'),
            'collected' => $summaries->sum('paid'),
            'collectionRate' => $billed > 0 ? round($summaries->sum('paid') / $billed * 100, 1) : null,
            'aging' => $service->agingTotals($summaries),
            'accountsWithBalance' => $summaries->where('balance', '>', 0)->count(),
            'totalRows' => ImportedFeeRecord::count(),
            'matchedRows' => ImportedFeeRecord::whereNotNull('student_id')->count(),
            'needsReview' => ImportedFeeRecord::unmatched()->where('is_held', false)->count(),
            'restrictedRows' => ImportedFeeRecord::where('is_restricted', true)->count(),
            'draftBatches' => ImportBatch::whereNull('published_at')->count(),
            'unconfirmedBatches' => ImportBatch::whereNull('restricted_confirmed_at')
                ->whereHas('importedFeeRecords', fn ($q) => $q->where('is_restricted', true))->count(),
            'recentBatches' => ImportBatch::withCount([
                'importedFeeRecords',
                'importedFeeRecords as matched_count' => fn ($q) => $q->whereNotNull('student_id'),
            ])->withSum('importedFeeRecords as billed_sum', 'amount')
                ->orderByDesc('uploaded_at')->take(5)->get(),
            'byPeriod' => $service->collectionRateByPeriod(),
        ]);
    }
}
