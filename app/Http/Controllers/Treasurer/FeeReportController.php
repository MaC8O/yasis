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
    /** §9.6 Fee Reports: school-wide answers, each with its own download. */
    public function index(FeeSummaryService $service)
    {
        $summaries = $service->studentSummaries();
        $owing = $summaries->where('balance', '>', 0);
        $billed = $summaries->sum('total_billed');
        $aging = $service->agingTotals($summaries);

        return view('treasurer.reports.index', [
            'billedTotal' => $billed,
            'paidTotal' => $summaries->sum('paid'),
            'owedTotal' => $summaries->sum('balance'),
            'collectionRate' => $billed > 0 ? round($summaries->sum('paid') / $billed * 100, 1) : null,
            'owingCount' => $owing->count(),
            'aging' => $aging,
            'agingCounts' => collect(array_keys(FeeSummaryService::AGING_BUCKETS))
                ->mapWithKeys(fn ($k) => [$k => $summaries->filter(fn ($s) => $s->aging[$k] > 0)->count()])->all(),
            'topOwing' => $owing->sortByDesc('balance')->take(10)->values(),
            'byPeriod' => $service->collectionRateByPeriod(),
            'byDepartment' => $service->outstandingByDepartment(summaries: $summaries),
            'statementStudents' => $summaries->map(fn ($s) => $s->student)->sortBy('name')->values(),
        ]);
    }

    /** "Print a student's statement" picker on Fee Reports → that student's statement page. */
    public function findStatement(Request $request)
    {
        $input = trim((string) $request->input('student'));
        $student = Student::where('student_id_number', $input)->first()
            ?? Student::where('name', $input)->first();

        if (! $student) {
            return redirect()->to(route('treasurer.reports.index').'#statement')
                ->with('warning', $input === '' ? 'Choose a student first.' : "No student found for “{$input}”.");
        }

        return redirect()->route('treasurer.records.show', $student);
    }

    /** Outstanding balance list — who owes what, with full names and contact for follow-up. */
    public function downloadOutstanding(Request $request, FeeSummaryService $service, AuditService $audit): StreamedResponse
    {
        $rows = $service->studentSummaries()->where('balance', '>', 0)->sortByDesc('balance');

        $audit->log($request->user(), 'Generated outstanding balance report', 'FeeReport', null);

        return $this->csv('outstanding-balances-'.now()->format('Y-m-d').'.csv',
            ['Student ID', 'Full name', 'Department', 'Class', 'Primary guardian', 'Guardian phone', 'Charged (MMK)', 'Paid (MMK)', 'Still owed (MMK)', 'Status', 'Days unpaid (if over 30)', 'Last charge date'],
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
                    $row->overdue_days ?? '',
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
            ['Student ID', 'Full name', 'Department', 'Class', 'Under 30 days (MMK)', '31-60 days (MMK)', '61-90 days (MMK)', 'Over 90 days (MMK)', 'Still owed (MMK)'],
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
