<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\FeeSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeReportController extends Controller
{
    public function index(FeeSummaryService $service)
    {
        $summaries = $service->studentSummaries();
        $distribution = $service->statusDistribution();
        $totalBilled = array_sum($distribution);

        return view('treasurer.reports.index', [
            'outstandingTotal' => $summaries->sum('balance'),
            'paidTotal' => $summaries->sum('paid'),
            'studentsWithBalance' => $summaries->where('balance', '>', 0)->count(),
            'distribution' => $distribution,
            'distributionPct' => $totalBilled > 0 ? [
                'paid' => round($distribution['paid'] / $totalBilled * 100, 1),
                'partial' => round($distribution['partial'] / $totalBilled * 100, 1),
                'outstanding' => round($distribution['outstanding'] / $totalBilled * 100, 1),
            ] : ['paid' => 0, 'partial' => 0, 'outstanding' => 0],
            'byDepartment' => $service->outstandingByDepartment(),
            'byPeriod' => $service->collectionRateByPeriod(),
        ]);
    }

    public function downloadOutstanding(Request $request, FeeSummaryService $service, AuditService $audit): StreamedResponse
    {
        $rows = $service->studentSummaries()->where('balance', '>', 0)->sortByDesc('balance');

        $audit->log($request->user(), 'Generated outstanding balance report', 'FeeReport', null);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student ID', 'Name', 'Department', 'Total Billed', 'Paid', 'Balance', 'Status']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->student->student_id_number,
                    $row->student->name,
                    $row->student->department->name ?? '',
                    $row->total_billed,
                    $row->paid,
                    $row->balance,
                    $row->status,
                ]);
            }
            fclose($out);
        }, 'outstanding-balances.csv');
    }

    public function downloadStatement(Request $request, Student $student, AuditService $audit)
    {
        $records = $student->importedFeeRecords()->orderByDesc('txn_date')->get();

        $audit->log($request->user(), 'Generated student fee statement', 'Student', $student->id);

        $pdf = Pdf::loadView('treasurer.reports.statement-pdf', ['student' => $student, 'records' => $records]);

        return $pdf->stream("fee-statement-{$student->student_id_number}.pdf");
    }
}
