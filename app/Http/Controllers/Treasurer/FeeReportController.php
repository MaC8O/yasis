<?php

namespace App\Http\Controllers\Treasurer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\GuardianFeeController;
use App\Models\Student;
use App\Models\SunAccountBalance;
use App\Models\SunAccountLink;
use App\Services\AuditService;
use App\Services\Sun\SunFamilyStatements;
use App\Services\Sun\SunFeeOverview;
use App\Support\LedgerFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Treasurer's Fee Reports, from the live Sun ledger (published or not): who owes, is
 * settled or is in credit, by grade and department; charges, payments and allowances by month
 * and category; and the Student Control balance at each import (T7).
 */
class FeeReportController extends Controller
{
    public function index(Request $request, SunFeeOverview $fees)
    {
        $years = SunAccountBalance::current()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year');
        $fy = (int) ($years->contains($request->integer('year')) ? $request->integer('year') : $years->first());
        $rows = $fy ? $fees->liveRows($fy) : collect();

        return view('treasurer.reports.index', [
            'years' => $years,
            'fy' => $fy,
            'distribution' => $fees->distribution($rows),
            'totals' => $fees->totals($rows),
            'byGrade' => $fees->groupBy($rows, fn ($r) => $r->section?->gradeLevel?->name),
            'byDepartment' => $fees->groupBy($rows, fn ($r) => $r->student->department?->name),
            'byMonth' => $fy ? $fees->byMonth($fy) : collect(),
            'byCategory' => $fy ? $fees->byCategory($fy) : collect(),
            'control' => $fees->controlHistory(),
        ]);
    }

    /** Find a student's ledger account by student ID or name. */
    public function findStatement(Request $request)
    {
        $query = trim((string) $request->input('student'));
        $student = $query === '' ? null : Student::where('student_id_number', $query)->first()
            ?? Student::where('name', 'like', "%{$query}%")->orderBy('name')->first();
        $link = $student ? SunAccountLink::active()->where('student_id', $student->id)->orderByDesc('from_fiscal_year')->first() : null;
        if (! $link) {
            return redirect()->to(route('treasurer.reports.index').'#statement')
                ->with('warning', $student ? "{$student->name} has no ledger account linked." : 'No student matches that ID or name.');
        }

        return redirect()->route('treasurer.ledger.account', $link->sun_account_id);
    }

    /** Students who owe, for following up: one row each, from the year's live ledger. */
    public function downloadOutstanding(Request $request, SunFeeOverview $fees, AuditService $audit): StreamedResponse
    {
        $fy = (int) ($request->integer('year') ?: SunAccountBalance::current()->max('fiscal_year'));
        $rows = $fy ? $fees->liveRows($fy)->where('status', 'Owing')->sortByDesc('closing') : collect();
        $audit->log($request->user(), 'Generated outstanding balance report', 'FeeReport', null, ['year' => $fy ? LedgerFormat::year($fy) : null]);

        return $this->csv('owing-'.($fy ? LedgerFormat::year($fy) : 'none').'-'.now()->format('Y-m-d').'.csv',
            ['Student ID', 'Full name', 'Department', 'Class', 'Primary guardian', 'Guardian phone', 'Ledger account', 'Opening (Ks)', 'Owed (Ks)'],
            $rows->map(function ($row) {
                $guardian = $this->primaryGuardian($row->student);

                return [
                    $row->student->student_id_number, $row->student->name, $row->student->department->name ?? '', $row->section?->name ?? '',
                    $guardian?->user?->name ?? '', $guardian?->phone ?? '', $row->balance->account->code ?? '',
                    (string) $row->balance->opening_balance, (string) $row->balance->closing_balance,
                ];
            }));
    }

    /** The family copy of a student's statement, exactly as the family sees it (published statements only). */
    public function downloadStatement(Request $request, Student $student, SunFamilyStatements $statements, AuditService $audit)
    {
        $audit->log($request->user(), 'Generated family-copy fee statement', 'Student', $student->id);

        return GuardianFeeController::pdf($student, $statements->forStudent($student));
    }

    private function primaryGuardian(Student $student)
    {
        $student->loadMissing('guardians.user');

        return $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
    }

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
