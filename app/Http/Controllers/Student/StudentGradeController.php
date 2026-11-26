<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\Term;
use App\Services\GradeService;
use App\Services\ResultService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * The student's own grades and report cards, from ResultService (the same figures the guardian
 * and the report card show): frozen for a locked term, provisional otherwise.
 */
class StudentGradeController extends Controller
{
    public function index(Request $request, ResultService $results, GradeService $grades)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $terms = $activeYear?->terms()->orderBy('sequence')->get() ?? collect();
        $term = $terms->firstWhere('id', $request->integer('term')) ?? $terms->where('start_date', '<=', today())->where('end_date', '>=', today())->first() ?? $terms->last();
        $report = $term ? $results->studentTerm($student, $term) : null;

        return view('student.grades.index', [
            'student' => $student,
            'terms' => $terms,
            'term' => $term,
            'report' => $report,
            'breakdown' => $report?->section ? $grades->categoryBreakdown($report->section, $term, $student) : [],
            'comment' => $term ? ReportCardComment::where('student_id', $student->id)->where('term_id', $term->id)->first() : null,
            'releasedTerms' => $terms->where('results_released', true),
        ]);
    }

    public function reportCard(Request $request, ResultService $results)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $term = Term::findOrFail($request->integer('term'));
        abort_unless($term->results_released, 403, 'This term\'s results have not been released yet.');

        $report = $results->studentTerm($student, $term);
        $comment = ReportCardComment::where('student_id', $student->id)->where('term_id', $term->id)->first();

        $pdf = Pdf::loadView('guardian.grades.report-card-pdf', ['child' => $student, 'term' => $term, 'report' => $report, 'comment' => $comment]);

        return $pdf->stream("report-card-{$student->student_id_number}-{$term->name}.pdf");
    }
}
