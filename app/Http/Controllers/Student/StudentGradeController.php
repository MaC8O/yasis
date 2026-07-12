<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Services\GradeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class StudentGradeController extends Controller
{
    protected function buildBreakdown($student, $term, GradeService $gradeService)
    {
        $assignments = TeachingAssignment::whereIn('section_id', $student->enrollments()->pluck('section_id'))
            ->with('subject')->get();

        return $assignments->map(function ($a) use ($student, $term, $gradeService) {
            $result = $term ? $gradeService->computeStudentResult($student, $a->section, $a->subject, $term) : ['pct' => null, 'letter' => null, 'gpa' => null, 'breakdown' => []];

            return (object) ['subject' => $a->subject->name, 'result' => $result];
        });
    }

    public function index(Request $request, GradeService $gradeService)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $terms = $activeYear?->terms()->orderBy('sequence')->get() ?? collect();
        $term = $terms->firstWhere('id', $request->integer('term')) ?? $terms->where('start_date', '<=', today())->where('end_date', '>=', today())->first() ?? $terms->last();

        $subjects = $this->buildBreakdown($student, $term, $gradeService);
        $validResults = $subjects->pluck('result.pct')->filter();
        $validGpas = $subjects->pluck('result.gpa')->filter();

        return view('student.grades.index', [
            'student' => $student,
            'terms' => $terms,
            'term' => $term,
            'subjects' => $subjects,
            'overallScore' => $validResults->isNotEmpty() ? round($validResults->avg(), 1) : null,
            'termGpa' => $validGpas->isNotEmpty() ? round($validGpas->avg(), 2) : null,
            'comment' => $term ? ReportCardComment::where('student_id', $student->id)->where('term_id', $term->id)->first() : null,
            'releasedTerms' => $terms->where('results_released', true),
        ]);
    }

    public function reportCard(Request $request, GradeService $gradeService)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $term = Term::findOrFail($request->integer('term'));
        abort_unless($term->results_released, 403, 'This term\'s results have not been released yet.');

        $subjects = $this->buildBreakdown($student, $term, $gradeService);
        $comment = ReportCardComment::where('student_id', $student->id)->where('term_id', $term->id)->first();

        $pdf = Pdf::loadView('guardian.grades.report-card-pdf', ['child' => $student, 'term' => $term, 'subjects' => $subjects, 'comment' => $comment]);

        return $pdf->stream("report-card-{$student->student_id_number}-{$term->name}.pdf");
    }
}
