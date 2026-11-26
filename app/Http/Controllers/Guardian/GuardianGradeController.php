<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\Term;
use App\Services\GradeService;
use App\Services\ResultService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * A guardian's read-only view of a linked child's grades and report cards, from ResultService (the
 * same figures the student and the report card show): frozen for a locked term, provisional otherwise.
 */
class GuardianGradeController extends Controller
{
    use ResolvesChild;

    public function index(Request $request, ResultService $results, GradeService $grades)
    {
        $children = $this->guardianChildren($request);
        $child = $this->selectedChild($request);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $terms = $activeYear?->terms()->orderBy('sequence')->get() ?? collect();
        $term = $terms->firstWhere('id', $request->integer('term')) ?? $terms->where('start_date', '<=', today())->where('end_date', '>=', today())->first() ?? $terms->last();
        $report = $term ? $results->studentTerm($child, $term) : null;

        return view('guardian.grades.index', [
            'children' => $children,
            'child' => $child,
            'terms' => $terms,
            'term' => $term,
            'report' => $report,
            'breakdown' => $report?->section ? $grades->categoryBreakdown($report->section, $term, $child) : [],
            'comment' => $term ? ReportCardComment::where('student_id', $child->id)->where('term_id', $term->id)->first() : null,
            'releasedTerms' => $terms->where('results_released', true),
        ]);
    }

    public function reportCard(Request $request, ResultService $results)
    {
        $child = $this->selectedChild($request);
        $term = Term::findOrFail($request->integer('term'));

        abort_unless($term->results_released, 403, 'This term\'s results have not been released yet.');

        $report = $results->studentTerm($child, $term);
        $comment = ReportCardComment::where('student_id', $child->id)->where('term_id', $term->id)->first();

        $pdf = Pdf::loadView('guardian.grades.report-card-pdf', compact('child', 'term', 'report', 'comment'));

        return $pdf->stream("report-card-{$child->student_id_number}-{$term->name}.pdf");
    }
}
