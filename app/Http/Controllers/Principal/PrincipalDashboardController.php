<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Grade;
use App\Models\GradeChangeRequest;
use App\Models\LeaveRequest;
use App\Models\PromotionBatch;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Services\FeeSummaryService;

class PrincipalDashboardController extends Controller
{
    public function index(FeeSummaryService $feeService)
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        $enrollmentByDepartment = Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])
            ->withCount(['students' => fn ($q) => $q->where('enrollment_status', 'Enrolled')])
            ->orderByDesc('students_count')->get();
        $totalEnrollment = $enrollmentByDepartment->sum('students_count');

        $todayAttendance = AttendanceRecord::whereDate('attendance_date', today())->get();
        $attendanceRate = $todayAttendance->count() > 0
            ? round($todayAttendance->whereIn('status', ['Present', 'Tardy', 'Excused'])->count() / $todayAttendance->count() * 100, 1)
            : null;

        $grades = Grade::with('assessment')->get();
        $academicAverage = $grades->count()
            ? round($grades->avg(fn ($g) => $g->assessment->max_score > 0 ? $g->score / $g->assessment->max_score * 100 : 0), 1)
            : null;

        $summaries = $feeService->studentSummaries();
        $totalBilled = $summaries->sum('total_billed');
        $feeCollectionRate = $totalBilled > 0 ? round($summaries->sum('paid') / $totalBilled * 100) : null;

        $pendingApprovals = PromotionBatch::where('status', 'VP_Approved')->count()
            + DocumentRequest::where('type', 'Transcript')->where('status', 'Approved')->count()
            + GradeChangeRequest::where('status', 'VP_Approved')->count();

        $staffToday = StaffAttendance::where('attendance_date', today()->toDateString())->get();

        // School-wide attendance rate per school day, most recent 10 recorded days.
        $attendanceTrend = AttendanceRecord::where('attendance_date', '>=', today()->subDays(21))
            ->get()
            ->groupBy(fn ($r) => $r->attendance_date instanceof \Carbon\CarbonInterface ? $r->attendance_date->toDateString() : (string) $r->attendance_date)
            ->sortKeys()
            ->take(-10)
            ->map(fn ($rows, $date) => [
                'label' => \Carbon\Carbon::parse($date)->format('M j'),
                'value' => round($rows->whereIn('status', ['Present', 'Tardy', 'Excused'])->count() / max($rows->count(), 1) * 100, 1),
            ])
            ->values()
            ->all();

        $feeDistribution = $feeService->statusDistribution();

        return view('principal.dashboard', [
            'attendanceTrend' => $attendanceTrend,
            'feeStatusSegments' => [
                ['label' => 'Paid', 'value' => $feeDistribution['paid'], 'color' => '#2E8B57'],
                ['label' => 'Partial', 'value' => $feeDistribution['partial'], 'color' => '#A8841B'],
                ['label' => 'Outstanding', 'value' => $feeDistribution['outstanding'], 'color' => '#B0392B'],
            ],
            'activeYear' => $activeYear,
            'totalEnrollment' => $totalEnrollment,
            'enrollmentByDepartment' => $enrollmentByDepartment,
            'attendanceRate' => $attendanceRate,
            'academicAverage' => $academicAverage,
            'feeCollectionRate' => $feeCollectionRate,
            'outstandingTotal' => $summaries->sum('balance'),
            'pendingApprovals' => $pendingApprovals,
            'staffHeadcount' => StaffProfile::whereIn('status', ['Active', 'On Leave', 'Probation'])->count(),
            'onLeaveToday' => $staffToday->where('status', 'On-Leave')->count(),
            'pendingLeave' => LeaveRequest::where('status', 'Pending')->count(),
        ]);
    }
}
