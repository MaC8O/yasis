<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\FeeSummaryService;
use Illuminate\Http\Request;

class ImportedFeeRecordController extends Controller
{
    /** §9.5: the accounts-receivable ledger — one row per student, with aging. */
    public function index(Request $request, FeeSummaryService $service)
    {
        $all = $service->studentSummaries();
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

        if ($request->string('aging')->value() === 'overdue') {
            $summaries = $summaries->filter(fn ($s) => $s->aging['days_31_60'] + $s->aging['days_61_90'] + $s->aging['over_90'] > 0);
        }

        $sort = $request->string('sort', 'name')->value();
        $summaries = match ($sort) {
            'balance' => $summaries->sortByDesc('balance'),
            'overdue' => $summaries->sortByDesc(fn ($s) => $s->aging['over_90'] * 1e6 + $s->aging['days_61_90'] * 1e3 + $s->aging['days_31_60']),
            default => $summaries->sortBy(fn ($s) => $s->student->name),
        };

        return view('treasurer.records.index', [
            'summaries' => $summaries->values(),
            'filters' => $request->only(['search', 'status', 'aging', 'sort']),
            'totals' => [
                'billed' => $summaries->sum('total_billed'),
                'paid' => $summaries->sum('paid'),
                'balance' => $summaries->sum('balance'),
                'aging' => $service->agingTotals($summaries),
            ],
            'stats' => [
                'receivables' => $all->sum('balance'),
                'accounts' => $all->where('balance', '>', 0)->count(),
                'overdue' => $all->sum(fn ($s) => $s->aging['days_31_60'] + $s->aging['days_61_90'] + $s->aging['over_90']),
                'restrictedRows' => ImportedFeeRecord::where('is_restricted', true)->count(),
            ],
        ]);
    }

    /** §9.8 / UI 9.8: a student's statement of account — charges, payments, running balance. */
    public function show(Request $request, Student $student, FeeSummaryService $service, AuditService $audit)
    {
        // §3.8 access logging: record who opened a student's financial record.
        $audit->log($request->user(), 'Viewed student financial record', 'Student', $student->id);

        $lines = $service->statementLines(
            ImportedFeeRecord::where('student_id', $student->id)->with('importBatch')->get()
        );

        return view('treasurer.records.show', [
            'student' => $student->load(['department', 'enrollments.section', 'guardians.user']),
            'lines' => $lines,
            'aging' => $service->aging($lines),
        ]);
    }
}
