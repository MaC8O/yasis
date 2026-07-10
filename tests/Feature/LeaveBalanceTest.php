<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Exercises the leave-balance ledger: submitting reserves days as "pending" so a
 * staff member can't double-book, HR approval moves them into "used", and a
 * rejection releases the reservation back to the available pool.
 */
class LeaveBalanceTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_submit_reserves_pending_then_approve_moves_it_to_used(): void
    {
        $this->seedRoles();
        $this->seedLeaveTypes();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');
        $annual = LeaveType::where('name', 'Annual')->firstOrFail();

        LeaveBalance::create(['staff_id' => $teacher->id, 'leave_type_id' => $annual->id, 'year' => now()->year, 'allocated' => 10, 'pending' => 0, 'used' => 0]);

        $this->actingAs($teacher->user)->post('/teacher/leave', [
            'leave_type_id' => $annual->id,
            'from_date' => today()->addDays(5)->toDateString(),
            'to_date' => today()->addDays(7)->toDateString(),
            'reason' => 'Family trip',
        ])->assertSessionHasNoErrors();

        $balance = LeaveBalance::where('staff_id', $teacher->id)->where('leave_type_id', $annual->id)->firstOrFail();
        $this->assertSame(3, $balance->pending);
        $this->assertSame(0, $balance->used);

        $leaveRequest = LeaveRequest::where('staff_id', $teacher->id)->firstOrFail();
        $this->assertSame('Pending', $leaveRequest->status);

        $this->actingAs($hr->user)->post("/hr_office/leave/{$leaveRequest->id}/approve")
            ->assertSessionHasNoErrors();

        $balance->refresh();
        $this->assertSame(0, $balance->pending);
        $this->assertSame(3, $balance->used);
        $this->assertSame('Approved', $leaveRequest->fresh()->status);

        $this->assertDatabaseHas('staff_attendance', [
            'staff_id' => $teacher->id,
            'attendance_date' => today()->addDays(5)->toDateString(),
            'status' => 'On-Leave',
            'leave_request_id' => $leaveRequest->id,
        ]);
    }

    public function test_rejection_releases_the_pending_reservation(): void
    {
        $this->seedRoles();
        $this->seedLeaveTypes();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');
        $annual = LeaveType::where('name', 'Annual')->firstOrFail();

        LeaveBalance::create(['staff_id' => $teacher->id, 'leave_type_id' => $annual->id, 'year' => now()->year, 'allocated' => 10, 'pending' => 0, 'used' => 0]);

        $this->actingAs($teacher->user)->post('/teacher/leave', [
            'leave_type_id' => $annual->id,
            'from_date' => today()->addDays(5)->toDateString(),
            'to_date' => today()->addDays(6)->toDateString(),
        ])->assertSessionHasNoErrors();

        $leaveRequest = LeaveRequest::where('staff_id', $teacher->id)->firstOrFail();

        $this->actingAs($hr->user)->post("/hr_office/leave/{$leaveRequest->id}/reject")
            ->assertSessionHasNoErrors();

        $balance = LeaveBalance::where('staff_id', $teacher->id)->where('leave_type_id', $annual->id)->firstOrFail();
        $this->assertSame(0, $balance->pending);
        $this->assertSame(0, $balance->used);
        $this->assertSame('Rejected', $leaveRequest->fresh()->status);
    }

    public function test_submit_is_rejected_when_balance_is_insufficient(): void
    {
        $this->seedRoles();
        $this->seedLeaveTypes();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $annual = LeaveType::where('name', 'Annual')->firstOrFail();

        LeaveBalance::create(['staff_id' => $teacher->id, 'leave_type_id' => $annual->id, 'year' => now()->year, 'allocated' => 2, 'pending' => 0, 'used' => 0]);

        $this->actingAs($teacher->user)->post('/teacher/leave', [
            'leave_type_id' => $annual->id,
            'from_date' => today()->addDays(5)->toDateString(),
            'to_date' => today()->addDays(9)->toDateString(),
        ])->assertSessionHasErrors('from_date');

        $this->assertSame(0, LeaveRequest::where('staff_id', $teacher->id)->count());
        $balance = LeaveBalance::where('staff_id', $teacher->id)->where('leave_type_id', $annual->id)->firstOrFail();
        $this->assertSame(0, $balance->pending);
    }

    public function test_unpaid_leave_does_not_touch_any_balance_ledger(): void
    {
        $this->seedRoles();
        $this->seedLeaveTypes();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');
        $unpaid = LeaveType::where('name', 'Unpaid')->firstOrFail();

        $this->actingAs($teacher->user)->post('/teacher/leave', [
            'leave_type_id' => $unpaid->id,
            'from_date' => today()->addDays(5)->toDateString(),
            'to_date' => today()->addDays(6)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, LeaveBalance::where('staff_id', $teacher->id)->where('leave_type_id', $unpaid->id)->count());

        $leaveRequest = LeaveRequest::where('staff_id', $teacher->id)->firstOrFail();
        $this->actingAs($hr->user)->post("/hr_office/leave/{$leaveRequest->id}/approve")->assertSessionHasNoErrors();

        $this->assertSame(0, LeaveBalance::where('staff_id', $teacher->id)->where('leave_type_id', $unpaid->id)->count());
        $this->assertSame('Approved', $leaveRequest->fresh()->status);
    }
}
