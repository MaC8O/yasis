<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportCardController;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\Term;
use App\Services\GradeService;
use App\Services\ReportCardService;
use App\Services\ResultService;
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

    /** The school's report card as a booklet PDF (released terms only). */
    public function reportCard(Request $request, ReportCardService $cards)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);
        $term = Term::findOrFail($request->integer('term'));
        ReportCardController::assertReleased($term);

        return ReportCardController::stream($cards->build($student, $term) ?? abort(404, 'No report card for this term.'));
    }

    /** The same report card on screen. */
    public function showReportCard(Request $request, Term $term, ReportCardService $cards)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);
        ReportCardController::assertReleased($term);

        return view('report-cards.show', [
            'card' => $cards->build($student, $term) ?? abort(404, 'No report card for this term.'),
            'pdfUrl' => route('student.grades.report-card', ['term' => $term->id]),
            'backUrl' => route('student.grades.index', ['term' => $term->id]),
            'portal' => 'student',
        ]);
    }
}
