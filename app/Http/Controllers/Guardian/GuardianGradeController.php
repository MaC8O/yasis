<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Http\Controllers\ReportCardController;
use App\Models\AcademicYear;
use App\Models\ReportAcknowledgement;
use App\Models\ReportCardComment;
use App\Models\Term;
use App\Services\GradeService;
use App\Services\ReportCardActionsService;
use App\Services\ReportCardService;
use App\Services\ResultService;
use Illuminate\Http\Request;

/**
 * A guardian's read-only view of a linked child's grades and report cards, from ResultService (the
 * same figures the student and the report card show): frozen for a locked term, provisional otherwise.
 * The guardian acknowledges each released report here.
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

    /** The school's report card as a booklet PDF (released terms only). */
    public function reportCard(Request $request, ReportCardService $cards)
    {
        $child = $this->selectedChild($request);
        $term = Term::findOrFail($request->integer('term'));
        ReportCardController::assertReleased($term);

        return ReportCardController::stream($cards->build($child, $term) ?? abort(404, 'No report card for this term.'));
    }

    /** The same report card on screen, with "I acknowledge this report". */
    public function showReportCard(Request $request, Term $term, ReportCardService $cards)
    {
        $child = $this->selectedChild($request);
        ReportCardController::assertReleased($term);

        return view('report-cards.show', [
            'card' => $cards->build($child, $term) ?? abort(404, 'No report card for this term.'),
            'pdfUrl' => route('guardian.grades.report-card', ['child' => $child->id, 'term' => $term->id]),
            'backUrl' => route('guardian.grades.index', ['child' => $child->id, 'term' => $term->id]),
            'portal' => 'guardian',
            'acknowledgeUrl' => route('guardian.report-cards.acknowledge', ['term' => $term->id, 'child' => $child->id]),
            'acknowledged' => ReportAcknowledgement::current()->where('term_id', $term->id)->where('student_id', $child->id)
                ->where('role', ReportAcknowledgement::GUARDIAN)->with('user')->first(),
        ]);
    }

    public function acknowledge(Request $request, Term $term, ReportCardActionsService $actions)
    {
        $child = $this->selectedChild($request);
        $actions->guardianAcknowledge($child, $term, $request->user());

        return back()->with('status', "Thank you. {$child->name}'s {$term->name} report is acknowledged.");
    }
}
