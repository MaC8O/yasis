<?php

namespace App\Http\Controllers\VpAcademic;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\PromotionBatch;

class VpDashboardController extends Controller
{
    public function index()
    {
        $departmentPerformance = Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])
            ->get()
            ->map(function ($department) {
                $grades = Grade::whereHas('student', fn ($q) => $q->where('department_id', $department->id))
                    ->with('assessment')->get();

                $avg = $grades->count()
                    ? round($grades->avg(fn ($g) => $g->assessment->max_score > 0 ? $g->score / $g->assessment->max_score * 100 : 0), 1)
                    : null;

                return (object) ['department' => $department->name, 'average' => $avg, 'gradedCount' => $grades->count()];
            });

        return view('vp_academic.dashboard', [
            'departmentPerformance' => $departmentPerformance,
            'pendingPromotions' => PromotionBatch::where('status', 'Pending')->count(),
            'pendingTranscripts' => DocumentRequest::where('type', 'Transcript')->where('status', 'Pending Approval')->count(),
        ]);
    }
}
