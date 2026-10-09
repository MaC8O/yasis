<?php

namespace App\Http\Controllers\Leadership;

use App\Http\Controllers\Controller;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\FeeSummaryService;
use App\Support\FeeVisibility;
use Illuminate\Http\Request;

/**
 * Read-only fee visibility for Principal / VP Academic / Registrar (§2.2 fees.view_readonly).
 * Everything here is scoped to FeeVisibility::LEADERSHIP — published, matched, non-held rows,
 * and restricted (SDA) rows only when the Treasurer's policy allows it.
 */
class FeeVisibilityController extends Controller
{
    public function index(Request $request, FeeSummaryService $service)
    {
        $role = $request->user()->getRoleNames()->first();
        $all = $service->studentSummaries(FeeVisibility::LEADERSHIP);
        $summaries = $all;

        if ($search = $request->string('search')->trim()->lower()->value()) {
            $summaries = $summaries->filter(fn ($s) => str_contains(mb_strtolower($s->student->name), $search)
                || str_contains(mb_strtolower($s->student->student_id_number), $search));
        }

        if ($status = $request->string('status')->value()) {
            $summaries = $status === 'Outstanding'
                ? $summaries->whereIn('status', ['Owed', 'Outstanding'])
                : $summaries->where('status', $status);
        }

        $billed = $all->sum('total_billed');

        return view('leadership.fees.index', [
            'role' => $role,
            'summaries' => $summaries->sortBy(fn ($s) => $s->student->name)->values(),
            'filters' => $request->only(['search', 'status']),
            'outstandingTotal' => $all->sum('balance'),
            'paidTotal' => $all->sum('paid'),
            'collectionRate' => $billed > 0 ? round($all->sum('paid') / $billed * 100, 1) : null,
            'aging' => $service->agingTotals($all),
            'byDepartment' => $service->outstandingByDepartment(summaries: $all),
            'showsRestricted' => FeeVisibility::leadershipSeesRestricted(),
        ]);
    }

    public function show(Request $request, Student $student, FeeSummaryService $service, AuditService $audit)
    {
        // §3.8 access logging: record who opened a student's financial record.
        $audit->log($request->user(), 'Viewed student financial record', 'Student', $student->id);

        $records = ImportedFeeRecord::query()->visibleTo(FeeVisibility::LEADERSHIP)
            ->where('student_id', $student->id)->with('importBatch')->get();
        $lines = $service->statementLines($records);

        return view('leadership.fees.show', [
            'role' => $request->user()->getRoleNames()->first(),
            'student' => $student->load(['department', 'enrollments.section', 'guardians.user']),
            'lines' => $lines,
            'aging' => $service->aging($lines),
        ]);
    }
}
