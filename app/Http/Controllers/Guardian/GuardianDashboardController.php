<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guardian\Concerns\ResolvesChild;
use App\Models\AcademicYear;
use App\Services\FeeSummaryService;
use App\Services\GradeService;
use Illuminate\Http\Request;

class GuardianDashboardController extends Controller
{
    use ResolvesChild;

    public function index(Request $request, GradeService $gradeService, FeeSummaryService $feeService)
    {
        $children = $this->guardianChildren($request);
        $child = $this->selectedChild($request);

        $attendance = $child->attendanceRecords()->get();
        $attendanceRate = $attendance->count()
            ? round($attendance->whereIn('status', ['Present', 'Tardy', 'Excused'])->count() / $attendance->count() * 100)
            : null;

        $activeYear = AcademicYear::where('is_active', true)->first();
        $currentTerm = $activeYear?->terms()->where('start_date', '<=', today())->where('end_date', '>=', today())->first();

        $assignments = \App\Models\TeachingAssignment::whereIn('section_id', $child->enrollments()->pluck('section_id'))->with('subject')->get();
        $gpas = $assignments->map(fn ($a) => $currentTerm
            ? $gradeService->computeStudentResult($child, $a->section, $a->subject, $currentTerm)['gpa']
            : null)->filter();

        $feeSummary = $feeService->studentSummaries(familyFacing: true)->firstWhere('student.id', $child->id);

        return view('guardian.dashboard', [
            'children' => $children,
            'child' => $child,
            'attendanceRate' => $attendanceRate,
            'latestGpa' => $gpas->isNotEmpty() ? round($gpas->avg(), 2) : null,
            'feeStatus' => $feeSummary?->status ?? 'No records',
            'attendanceSegments' => [
                ['label' => 'Present', 'value' => $attendance->where('status', 'Present')->count(), 'color' => '#2E8B57'],
                ['label' => 'Tardy', 'value' => $attendance->where('status', 'Tardy')->count(), 'color' => '#A8841B'],
                ['label' => 'Excused', 'value' => $attendance->where('status', 'Excused')->count(), 'color' => '#2E5AAC'],
                ['label' => 'Absent', 'value' => $attendance->where('status', 'Absent')->count(), 'color' => '#B0392B'],
            ],
        ]);
    }
}
