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
    /** Tabs on Student Accounts: which accounts to list. */
    public const VIEWS = [
        'all' => 'All students',
        'owing' => 'Still owe money',
        'overdue' => 'Overdue (30+ days)',
        'paid' => 'Fully paid',
    ];

    /** §9.5 Student Accounts: find a student and see what they owe. */
    public function index(Request $request, FeeSummaryService $service)
    {
        $all = $service->studentSummaries();

        $view = array_key_exists($request->string('view')->value(), self::VIEWS) ? $request->string('view')->value() : 'all';
        $filters = [
            'all' => fn ($s) => true,
            'owing' => fn ($s) => $s->balance > 0,
            'overdue' => fn ($s) => $s->overdue_days !== null,
            'paid' => fn ($s) => $s->balance <= 0,
        ];
        $counts = collect($filters)->map(fn ($f) => $all->filter($f)->count());

        $summaries = $all->filter($filters[$view]);

        if ($search = $request->string('search')->trim()->lower()->value()) {
            $summaries = $summaries->filter(fn ($s) => str_contains(mb_strtolower($s->student->name), $search)
                || str_contains(mb_strtolower($s->student->student_id_number), $search)
                || str_contains(mb_strtolower((string) $s->section?->name), $search));
        }

        $sort = $request->string('sort', 'name')->value();
        $summaries = match ($sort) {
            'owed' => $summaries->sortByDesc('balance'),
            'overdue' => $summaries->sortByDesc(fn ($s) => $s->overdue_days ?? -1),
            default => $summaries->sortBy(fn ($s) => $s->student->name),
        };

        return view('treasurer.records.index', [
            'summaries' => $summaries->values(),
            'view' => $view,
            'views' => self::VIEWS,
            'counts' => $counts,
            'filters' => ['search' => $request->string('search')->value(), 'sort' => $sort],
            'totals' => [
                'charged' => $summaries->sum('total_billed'),
                'paid' => $summaries->sum('paid'),
                'owed' => $summaries->sum('balance'),
            ],
            'owedTotal' => $all->sum('balance'),
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
