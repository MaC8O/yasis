<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrStaffAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? today();

        $staff = StaffProfile::with('user', 'department')
            ->whereIn('staff_profiles.status', ['Active', 'On Leave', 'Probation'])
            ->join('users', 'users.id', '=', 'staff_profiles.id')->orderBy('users.name')
            ->select('staff_profiles.*')->get();

        $existing = StaffAttendance::where('attendance_date', $date->toDateString())->get()->keyBy('staff_id');

        $approvedLeave = LeaveRequest::where('status', 'Approved')
            ->where('from_date', '<=', $date->toDateString())
            ->where('to_date', '>=', $date->toDateString())
            ->get()->keyBy('staff_id');

        return view('hr.attendance.index', [
            'date' => $date,
            'staff' => $staff,
            'existing' => $existing,
            'approvedLeave' => $approvedLeave,
            'markedCount' => $existing->count(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'attendance_date' => ['required', 'date'],
            'statuses' => ['required', 'array'],
            'statuses.*.staff_id' => ['required', 'exists:staff_profiles,id'],
            'statuses.*.status' => ['required', Rule::in(['Present', 'Absent', 'Tardy', 'On-Leave'])],
            'statuses.*.remark' => ['nullable', 'string', 'max:150'],
        ]);

        $hr = $request->user()->staffProfile;

        foreach ($data['statuses'] as $row) {
            $leave = LeaveRequest::where('staff_id', $row['staff_id'])->where('status', 'Approved')
                ->where('from_date', '<=', $data['attendance_date'])
                ->where('to_date', '>=', $data['attendance_date'])->first();

            StaffAttendance::updateOrCreate(
                ['staff_id' => $row['staff_id'], 'attendance_date' => $data['attendance_date']],
                [
                    'status' => $row['status'],
                    'remark' => $row['remark'] ?? null,
                    'leave_request_id' => $row['status'] === 'On-Leave' ? $leave?->id : null,
                    'recorded_by' => $hr->id,
                ]
            );
        }

        $audit->log($request->user(), 'Recorded staff attendance', 'StaffAttendance', null);

        return back()->with('status', 'Staff attendance saved for '.\Carbon\Carbon::parse($data['attendance_date'])->format('M j, Y').'.');
    }
}
