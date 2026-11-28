<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\SunAccountBalance;
use App\Models\SunAccountReview;
use App\Models\SunImportBatch;
use App\Services\Sun\SunAccountService;
use App\Services\Sun\SunFeeOverview;
use App\Support\LedgerFormat;

/** The Treasurer's dashboard: the latest financial year's ledger and what is waiting for review. */
class TreasurerDashboardController extends Controller
{
    public function index(SunFeeOverview $fees, SunAccountService $accounts)
    {
        $fy = SunAccountBalance::current()->max('fiscal_year');
        $rows = $fy ? $fees->liveRows($fy) : collect();

        return view('treasurer.dashboard', [
            'yearLabel' => $fy ? LedgerFormat::year($fy) : null,
            'fy' => $fy,
            'distribution' => $fees->distribution($rows),
            'totals' => $fees->totals($rows),
            'control' => $fy ? SunAccountBalance::current()->where('fiscal_year', $fy)->where('is_control', true)->value('closing_balance') : null,
            'unmatched' => $fy ? $accounts->unmatchedQuery($fy)->count() : 0,
            'pendingReviews' => SunAccountReview::pending()->count(),
            'unpublished' => SunImportBatch::current()->whereNull('published_at')->count(),
            'recentBatches' => SunImportBatch::current()->orderByDesc('id')->take(5)->get(),
        ]);
    }
}
