<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Mail\LeaveDecisionMail;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Services\AuditService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HrLeaveManagementController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->string('tab')->value() ?: 'Pending';

        return view('hr.leave.index', [
            'tab' => $tab,
            'requests' => LeaveRequest::where('status', $tab)->with(['staff.user', 'leaveType', 'submittedBy.user'])->latest('created_at')->get(),
            'counts' => [
                'Pending' => LeaveRequest::where('status', 'Pending')->count(),
                'Approved' => LeaveRequest::where('status', 'Approved')->count(),
                'Rejected' => LeaveRequest::where('status', 'Rejected')->count(),
            ],
            'balances' => LeaveBalance::where('year', now()->year)->with(['staff.user', 'leaveType'])->get()->groupBy('staff_id'),
            'staffList' => StaffProfile::with('user')->whereIn('status', ['Active', 'On Leave', 'Probation'])->get(),
            'leaveTypes' => LeaveType::all(),
        ]);
    }

    protected function days(string $from, string $to): int
    {
        return Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
    }

    public function submitOnBehalf(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff_profiles,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $hr = $request->user()->staffProfile;
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $days = $this->days($data['from_date'], $data['to_date']);

        if ($leaveType->name !== 'Unpaid') {
            $balance = LeaveBalance::firstOrCreate(
                ['staff_id' => $data['staff_id'], 'leave_type_id' => $leaveType->id, 'year' => now()->year],
                ['allocated' => 0, 'pending' => 0, 'used' => 0]
            );

            if ($balance->allocated - $balance->used - $balance->pending < $days) {
                throw ValidationException::withMessages(['from_date' => "Insufficient {$leaveType->name} balance for {$days} day(s)."]);
            }
            $balance->increment('pending', $days);
        }

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $data['staff_id'],
            'leave_type_id' => $leaveType->id,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => 'Pending',
            'submitted_by' => $hr->id,
        ]);

        $audit->log($request->user(), 'Submitted leave on behalf of staff', 'LeaveRequest', $leaveRequest->id);

        return back()->with('status', 'Leave request entered on behalf of staff.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, AuditService $audit, NotificationService $notifier)
    {
        abort_unless($leaveRequest->status === 'Pending', 403);

        $hr = $request->user()->staffProfile;

        if ($leaveRequest->leaveType->name !== 'Unpaid') {
            $balanceQuery = LeaveBalance::where('staff_id', $leaveRequest->staff_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)->where('year', now()->year);
            $balanceQuery->decrement('pending', $leaveRequest->days);
            $balanceQuery->increment('used', $leaveRequest->days);
        }

        $leaveRequest->update(['status' => 'Approved', 'decided_by' => $hr->id, 'decided_at' => now()]);

        foreach (CarbonPeriod::create($leaveRequest->from_date, $leaveRequest->to_date) as $date) {
            StaffAttendance::updateOrCreate(
                ['staff_id' => $leaveRequest->staff_id, 'attendance_date' => $date->toDateString()],
                ['status' => 'On-Leave', 'leave_request_id' => $leaveRequest->id, 'recorded_by' => $hr->id]
            );
        }

        $audit->log($request->user(), 'Approved leave request', 'LeaveRequest', $leaveRequest->id);

        $leaveRequest->load(['staff.user', 'leaveType', 'decidedBy.user']);
        $notifier->sendEmail($leaveRequest->staff->user->email, new LeaveDecisionMail($leaveRequest));

        return back()->with('status', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, AuditService $audit, NotificationService $notifier)
    {
        abort_unless($leaveRequest->status === 'Pending', 403);

        $hr = $request->user()->staffProfile;

        if ($leaveRequest->leaveType->name !== 'Unpaid') {
            LeaveBalance::where('staff_id', $leaveRequest->staff_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)->where('year', now()->year)
                ->decrement('pending', $leaveRequest->days);
        }

        $leaveRequest->update(['status' => 'Rejected', 'decided_by' => $hr->id, 'decided_at' => now()]);
        $audit->log($request->user(), 'Rejected leave request', 'LeaveRequest', $leaveRequest->id);

        $leaveRequest->load(['staff.user', 'leaveType', 'decidedBy.user']);
        $notifier->sendEmail($leaveRequest->staff->user->email, new LeaveDecisionMail($leaveRequest));

        return back()->with('status', 'Leave request rejected.');
    }
}
