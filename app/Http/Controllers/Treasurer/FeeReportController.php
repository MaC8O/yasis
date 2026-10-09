<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\ImportedFeeRecord;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\FeeSummaryService;
use App\Support\FeeVisibility;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeReportController extends Controller
{
    public function index(FeeSummaryService $service)
    {
        $summaries = $service->studentSummaries();
        $distribution = $service->statusDistribution();
        $totalBilled = array_sum($distribution);

        return view('treasurer.reports.index', [
            'billedTotal' => $summaries->sum('total_billed'),
            'outstandingTotal' => $summaries->sum('balance'),
            'paidTotal' => $summaries->sum('paid'),
            'studentsWithBalance' => $summaries->where('balance', '>', 0)->count(),
            'aging' => $service->agingTotals($summaries),
            'distribution' => $distribution,
            'distributionPct' => $totalBilled > 0 ? [
                'paid' => round($distribution['paid'] / $totalBilled * 100, 1),
                'partial' => round($distribution['partial'] / $totalBilled * 100, 1),
                'outstanding' => round($distribution['outstanding'] / $totalBilled * 100, 1),
            ] : ['paid' => 0, 'partial' => 0, 'outstanding' => 0],
            'byDepartment' => $service->outstandingByDepartment(summaries: $summaries),
            'byPeriod' => $service->collectionRateByPeriod(),
            'topDebtors' => $summaries->where('balance', '>', 0)->sortByDesc('balance')->take(10)->values(),
        ]);
    }

    /** Outstanding balance list — who owes what, with full names and contact for follow-up. */
    public function downloadOutstanding(Request $request, FeeSummaryService $service, AuditService $audit): StreamedResponse
    {
        $rows = $service->studentSummaries()->where('balance', '>', 0)->sortByDesc('balance');

        $audit->log($request->user(), 'Generated outstanding balance report', 'FeeReport', null);

        return $this->csv('outstanding-balances-'.now()->format('Y-m-d').'.csv',
            ['Student ID', 'Full name', 'Department', 'Class', 'Primary guardian', 'Guardian phone', 'Total billed (MMK)', 'Paid (MMK)', 'Balance (MMK)', 'Status', 'Last activity'],
            $rows->map(function ($row) {
                $guardian = $this->primaryGuardian($row->student);

                return [
                    $row->student->student_id_number,
                    $row->student->name,
                    $row->student->department->name ?? '',
                    $row->section?->name ?? '',
                    $guardian?->user?->name ?? '',
                    $guardian?->phone ?? $guardian?->user?->phone ?? '',
                    $row->total_billed,
                    $row->paid,
                    $row->balance,
                    $row->status,
                    $row->last_activity?->format('Y-m-d'),
                ];
            }));
    }

    /** Aged receivables — the balance of every account split by how long it has been unpaid. */
    public function downloadAging(Request $request, FeeSummaryService $service, AuditService $audit): StreamedResponse
    {
        $rows = $service->studentSummaries()->where('balance', '>', 0)->sortBy(fn ($s) => $s->student->name);

        $audit->log($request->user(), 'Generated aged receivables report', 'FeeReport', null);

        $totals = $service->agingTotals($rows);

        return $this->csv('aged-receivables-'.now()->format('Y-m-d').'.csv',
            ['Student ID', 'Full name', 'Department', 'Class', 'Current 0-30 (MMK)', '31-60 days (MMK)', '61-90 days (MMK)', 'Over 90 days (MMK)', 'Total balance (MMK)'],
            $rows->map(fn ($row) => [
                $row->student->student_id_number,
                $row->student->name,
                $row->student->department->name ?? '',
                $row->section?->name ?? '',
                $row->aging['current'],
                $row->aging['days_31_60'],
                $row->aging['days_61_90'],
                $row->aging['over_90'],
                $row->balance,
            ])->push(['', 'TOTAL', '', '', $totals['current'], $totals['days_31_60'], $totals['days_61_90'], $totals['over_90'], $rows->sum('balance')]));
    }

    /**
     * Statement of account PDF. ?copy=family produces the copy handed to guardians: it is cut
     * with the family visibility rules (no restricted/held/unpublished lines — §9.6).
     */
    public function downloadStatement(Request $request, Student $student, FeeSummaryService $service, AuditService $audit)
    {
        $familyCopy = $request->string('copy')->value() === 'family';
        $audience = $familyCopy ? FeeVisibility::FAMILY : FeeVisibility::TREASURER;

        $lines = $service->statementLines(
            ImportedFeeRecord::query()->visibleTo($audience)->where('student_id', $student->id)->with('importBatch')->get()
        );

        $audit->log($request->user(), $familyCopy ? 'Generated student fee statement (family copy)' : 'Generated student fee statement (office copy)', 'Student', $student->id);

        $pdf = Pdf::loadView('documents.pdf.fee-statement', [
            'student' => $student->load(['department', 'enrollments.section', 'guardians.user']),
            'guardian' => $this->primaryGuardian($student),
            'lines' => $lines,
            'aging' => $service->aging($lines),
            'familyCopy' => $familyCopy,
        ]);

        return $pdf->stream("statement-{$student->student_id_number}".($familyCopy ? '-family' : '').'.pdf');
    }

    private function primaryGuardian(Student $student)
    {
        $student->loadMissing('guardians.user');

        return $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
    }

    /** UTF-8 CSV with a BOM so Excel shows Myanmar names correctly. */
    private function csv(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
