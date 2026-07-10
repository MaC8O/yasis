<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeacherLeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user()->staffProfile;
        $year = now()->year;

        $leaveTypes = LeaveType::all();
        $balances = $leaveTypes->mapWithKeys(function ($type) use ($teacher, $year) {
            $balance = LeaveBalance::firstOrCreate(
                ['staff_id' => $teacher->id, 'leave_type_id' => $type->id, 'year' => $year],
                ['allocated' => 0, 'pending' => 0, 'used' => 0]
            );

            return [$type->id => $balance];
        });

        return view('teacher.leave.index', [
            'leaveTypes' => $leaveTypes,
            'balances' => $balances,
            'requests' => LeaveRequest::where('staff_id', $teacher->id)->with('leaveType')->latest()->get(),
        ]);
    }

    protected function days(string $from, string $to): int
    {
        return Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $teacher = $request->user()->staffProfile;
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $days = $this->days($data['from_date'], $data['to_date']);

        if ($leaveType->name !== 'Unpaid') {
            $balance = LeaveBalance::firstOrCreate(
                ['staff_id' => $teacher->id, 'leave_type_id' => $leaveType->id, 'year' => now()->year],
                ['allocated' => 0, 'pending' => 0, 'used' => 0]
            );

            if ($balance->allocated - $balance->used - $balance->pending < $days) {
                throw ValidationException::withMessages(['from_date' => "Insufficient {$leaveType->name} balance for {$days} day(s)."]);
            }

            $balance->increment('pending', $days);
        }

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $teacher->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => 'Pending',
            'submitted_by' => $teacher->id,
        ]);

        $audit->log($request->user(), 'Submitted leave request', 'LeaveRequest', $leaveRequest->id);

        return redirect()->route('teacher.leave.index')->with('status', 'Leave request submitted — routed to HR.');
    }

    public function update(Request $request, LeaveRequest $leaveRequest, AuditService $audit)
    {
        $teacher = $request->user()->staffProfile;
        abort_unless($leaveRequest->staff_id === $teacher->id && $leaveRequest->status === 'Pending', 403);

        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $newDays = $this->days($data['from_date'], $data['to_date']);

        if ($leaveRequest->leaveType->name !== 'Unpaid') {
            $balance = LeaveBalance::where('staff_id', $teacher->id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)->where('year', now()->year)->first();

            $delta = $newDays - $leaveRequest->days;
            if ($delta > 0 && $balance && ($balance->allocated - $balance->used - $balance->pending) < $delta) {
                throw ValidationException::withMessages(['from_date' => 'Insufficient balance for the extended range.']);
            }
            $balance?->increment('pending', $delta);
        }

        $leaveRequest->update(array_merge($data, ['days' => $newDays]));
        $audit->log($request->user(), 'Edited leave request', 'LeaveRequest', $leaveRequest->id);

        return redirect()->route('teacher.leave.index')->with('status', 'Leave request updated.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, AuditService $audit)
    {
        $teacher = $request->user()->staffProfile;
        abort_unless($leaveRequest->staff_id === $teacher->id && $leaveRequest->status === 'Pending', 403);

        if ($leaveRequest->leaveType->name !== 'Unpaid') {
            LeaveBalance::where('staff_id', $teacher->id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)->where('year', now()->year)
                ->decrement('pending', $leaveRequest->days);
        }

        $leaveRequest->update(['status' => 'Cancelled']);
        $audit->log($request->user(), 'Cancelled leave request', 'LeaveRequest', $leaveRequest->id);

        return back()->with('status', 'Leave request cancelled.');
    }
}
