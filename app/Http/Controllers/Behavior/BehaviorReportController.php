<?php

namespace App\Http\Controllers\Behavior;

use App\Http\Controllers\Controller;
use App\Http\Requests\Behavior\PointsRequest;
use App\Http\Requests\Behavior\ReasonRequest;
use App\Http\Requests\Behavior\StoreReportRequest;
use App\Http\Requests\Behavior\VpCreateReportRequest;
use App\Models\BehaviorCategory;
use App\Models\BehaviorReport;
use App\Models\Student;
use App\Services\Behavior\BehaviorAccess;
use App\Services\Behavior\BehaviorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class BehaviorReportController extends Controller
{
    public function __construct(private BehaviorService $behavior, private BehaviorAccess $access) {}

    /** One report. Teachers can always open reports they wrote, even for students no longer in their scope. */
    public function show(Request $request, BehaviorReport $report)
    {
        if (! $this->access->canViewReport($request->user(), $report)) {
            throw new AuthorizationException('You do not have access to this report.');
        }

        return view('behavior.reports.show', [
            'report' => $report->load(['student', 'category', 'reporter.user', 'reviewer.user', 'voider.user', 'versions.category', 'deduction.restorations']),
            'canOpenStudent' => $this->access->canViewStudent($request->user(), $report->student),
        ]);
    }

    // ---- Teacher

    public function create(Request $request, Student $student)
    {
        $this->access->assertTeacherCanReport($request->user(), $student);

        return view('behavior.reports.form', [
            'student' => $student, 'report' => null,
            'categories' => BehaviorCategory::active()->ordered()->get(),
        ]);
    }

    public function store(StoreReportRequest $request, Student $student)
    {
        $report = $this->behavior->submitReport($request->user(), $student, $request->validatedReport());

        return redirect()->route('behavior.reports.show', $report)
            ->with('status', "Report submitted. {$student->name} and their guardians can see it as “Awaiting review”; it does not change the score until the Vice Principal reviews it.");
    }

    public function edit(Request $request, BehaviorReport $report)
    {
        if ((int) $report->reported_by !== (int) $request->user()->id || $report->source !== 'Teacher') {
            throw new AuthorizationException('You can only edit reports you wrote yourself.');
        }

        $categories = BehaviorCategory::active()->ordered()->get();
        if (! $categories->contains('id', $report->behavior_category_id)) {
            $categories->push($report->category); // keep a since-deactivated category selectable as-is
        }

        return view('behavior.reports.form', ['student' => $report->student, 'report' => $report, 'categories' => $categories]);
    }

    public function update(StoreReportRequest $request, BehaviorReport $report)
    {
        $this->behavior->editReport($request->user(), $report, $request->validatedReport());

        return redirect()->route('behavior.reports.show', $report)->with('status', 'Report updated. The earlier version is kept and shown on the report.');
    }

    public function void(ReasonRequest $request, BehaviorReport $report)
    {
        $this->behavior->voidReport($request->user(), $report, $request->reason());

        return redirect()->route('behavior.reports.show', $report)->with('status', 'Report withdrawn. It stays visible as “Withdrawn” with your reason.');
    }

    // ---- VP

    public function vpStore(VpCreateReportRequest $request, Student $student)
    {
        $entry = $this->behavior->createAndDeduct($request->user(), $student, $request->validatedReport(), (int) $request->validated('points'), trim($request->validated('reason')));

        return redirect()->route('behavior.students.show', $student)->with('status', $this->deductedMessage($entry));
    }

    public function dismiss(ReasonRequest $request, BehaviorReport $report)
    {
        $this->behavior->dismiss($request->user(), $report, $request->reason());

        return back()->with('status', 'Report dismissed with no deduction.');
    }

    public function deduct(PointsRequest $request, BehaviorReport $report)
    {
        $entry = $this->behavior->deduct($request->user(), $report, (int) $request->validated('points'), trim($request->validated('reason')));

        return back()->with('status', $this->deductedMessage($entry));
    }

    private function deductedMessage($entry): string
    {
        return $entry->points_applied < $entry->points_requested
            ? "{$entry->points_requested} points entered, but the score was {$entry->score_before}, so {$entry->points_applied} were applied. Score is now 0."
            : "{$entry->points_applied} point(s) deducted. Score is now {$entry->score_after}.";
    }
}
