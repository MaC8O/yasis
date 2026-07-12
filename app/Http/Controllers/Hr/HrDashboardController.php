<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;

class HrDashboardController extends Controller
{
    public function index()
    {
        $totalStaff = StaffProfile::whereIn('status', ['Active', 'On Leave', 'Probation'])->count();
        $today = StaffAttendance::where('attendance_date', today()->toDateString())->get();

        return view('hr.dashboard', [
            'totalStaff' => $totalStaff,
            'activeToday' => $today->whereIn('status', ['Present', 'Tardy'])->count(),
            'onLeaveToday' => $today->where('status', 'On-Leave')->count(),
            'attendanceRate' => $today->count() > 0 ? round($today->whereIn('status', ['Present', 'Tardy'])->count() / $today->count() * 100) : 0,
            'markedToday' => $today->count(),
            'pendingCount' => LeaveRequest::where('status', 'Pending')->count(),
            'pendingRequests' => LeaveRequest::where('status', 'Pending')->with(['staff.user', 'leaveType'])->latest()->take(3)->get(),
            'headcountByDepartment' => Department::withCount(['staffProfiles' => fn ($q) => $q->whereIn('status', ['Active', 'On Leave', 'Probation'])])
                ->having('staff_profiles_count', '>', 0)->orderByDesc('staff_profiles_count')->get(),
            'leaveStatusSegments' => [
                ['label' => 'Approved', 'value' => LeaveRequest::where('status', 'Approved')->whereYear('from_date', now()->year)->count(), 'color' => '#2E8B57'],
                ['label' => 'Pending', 'value' => LeaveRequest::where('status', 'Pending')->count(), 'color' => '#A8841B'],
                ['label' => 'Rejected', 'value' => LeaveRequest::where('status', 'Rejected')->whereYear('from_date', now()->year)->count(), 'color' => '#B0392B'],
            ],
            'activeYear' => AcademicYear::where('is_active', true)->first(),
        ]);
    }
}
