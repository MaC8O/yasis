<?php

namespace App\Http\Controllers\Leadership;

use App\Http\Controllers\Controller;
use App\Services\FeeSummaryService;
use Illuminate\Http\Request;

class FeeVisibilityController extends Controller
{
    public function index(Request $request, FeeSummaryService $service)
    {
        $role = $request->user()->getRoleNames()->first();
        $summaries = $service->studentSummaries();

        return view('leadership.fees.index', [
            'role' => $role,
            'summaries' => $summaries->sortBy(fn ($s) => $s->student->first_name)->values(),
            'outstandingTotal' => $summaries->sum('balance'),
            'paidTotal' => $summaries->sum('paid'),
            'byDepartment' => $service->outstandingByDepartment(),
        ]);
    }
}
