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
use App\Services\ReportCardActionsService;
use App\Services\Sun\SunFeeOverview;

class PrincipalDashboardController extends Controller
{
    public function index(SunFeeOverview $fees)
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

        // §2.2: the Principal reads fees as leadership: the published statements of the latest
        // financial year, the same figures families see.
        $feeRows = $fees->publishedRows();
        $feeDistribution = $fees->distribution($feeRows);
        $feeTotals = $fees->totals($feeRows);

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

        return view('principal.dashboard', [
            'attendanceFlags' => app(ReportCardActionsService::class)->flagSummary(),
            'attendanceTrend' => $attendanceTrend,
            'feeStatusSegments' => [
                ['label' => 'Owing', 'value' => $feeDistribution['Owing'], 'color' => '#B0392B'],
                ['label' => 'Settled', 'value' => $feeDistribution['Settled'], 'color' => '#2E8B57'],
                ['label' => 'In credit', 'value' => $feeDistribution['In credit'], 'color' => '#3B6EA5'],
            ],
            'activeYear' => $activeYear,
            'totalEnrollment' => $totalEnrollment,
            'enrollmentByDepartment' => $enrollmentByDepartment,
            'attendanceRate' => $attendanceRate,
            'academicAverage' => $academicAverage,
            'feeOwingCount' => $feeDistribution['Owing'],
            'feeStudents' => $feeRows->count(),
            'outstandingTotal' => $feeTotals['owing'],
            'creditTotal' => $feeTotals['credit'],
            'pendingApprovals' => $pendingApprovals,
            'staffHeadcount' => StaffProfile::whereIn('status', ['Active', 'On Leave', 'Probation'])->count(),
            'onLeaveToday' => $staffToday->where('status', 'On-Leave')->count(),
            'pendingLeave' => LeaveRequest::where('status', 'Pending')->count(),
        ]);
    }
}
