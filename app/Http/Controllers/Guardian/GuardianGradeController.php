<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\TeachingAssignment;
use App\Services\GradeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class GuardianGradeController extends Controller
{
    use ResolvesChild;

    protected function buildBreakdown($child, $term, GradeService $gradeService)
    {
        $assignments = TeachingAssignment::whereIn('section_id', $child->enrollments()->pluck('section_id'))
            ->with('subject')->get();

        return $assignments->map(function ($a) use ($child, $term, $gradeService) {
            $result = $term ? $gradeService->computeStudentResult($child, $a->section, $a->subject, $term) : ['pct' => null, 'letter' => null, 'gpa' => null, 'breakdown' => []];

            return (object) ['subject' => $a->subject->name, 'result' => $result];
        });
    }

    public function index(Request $request, GradeService $gradeService)
    {
        $children = $this->guardianChildren($request);
        $child = $this->selectedChild($request);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $terms = $activeYear?->terms()->orderBy('sequence')->get() ?? collect();
        $term = $terms->firstWhere('id', $request->integer('term')) ?? $terms->where('start_date', '<=', today())->where('end_date', '>=', today())->first() ?? $terms->last();

        $subjects = $this->buildBreakdown($child, $term, $gradeService);
        $validResults = $subjects->pluck('result.pct')->filter();
        $validGpas = $subjects->pluck('result.gpa')->filter();

        return view('guardian.grades.index', [
            'children' => $children,
            'child' => $child,
            'terms' => $terms,
            'term' => $term,
            'subjects' => $subjects,
            'overallScore' => $validResults->isNotEmpty() ? round($validResults->avg(), 1) : null,
            'termGpa' => $validGpas->isNotEmpty() ? round($validGpas->avg(), 2) : null,
            'comment' => $term ? ReportCardComment::where('student_id', $child->id)->where('term_id', $term->id)->first() : null,
            'releasedTerms' => $terms->where('results_released', true),
        ]);
    }

    public function reportCard(Request $request, GradeService $gradeService)
    {
        $child = $this->selectedChild($request);
        $term = \App\Models\Term::findOrFail($request->integer('term'));

        abort_unless($term->results_released, 403, 'This term\'s results have not been released yet.');

        $subjects = $this->buildBreakdown($child, $term, $gradeService);
        $comment = ReportCardComment::where('student_id', $child->id)->where('term_id', $term->id)->first();

        $pdf = Pdf::loadView('guardian.grades.report-card-pdf', compact('child', 'term', 'subjects', 'comment'));

        return $pdf->stream("report-card-{$child->student_id_number}-{$term->name}.pdf");
    }
}
