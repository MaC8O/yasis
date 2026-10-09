<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class HrStaffPanelTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_index_shows_the_panel_and_double_click_opens_the_full_record(): void
    {
        $this->seedRoles();
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local', ['staff_id_number' => 'T-5001']);

        $this->actingAs($hr->user)->get(route('hr_office.staff.index', ['selected' => $teacher->id]))
            ->assertOk()
            ->assertSee('Staff information', false)
            ->assertSee('T-5001')
            ->assertSee('@dblclick="open(\''.route('hr_office.staff.show', $teacher).'\')"', false);
    }

    public function test_panel_shows_teaching_load_leave_and_attendance(): void
    {
        $this->seedRoles();
        $this->seedLeaveTypes();
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local', ['phone' => '09-777']);
        $department = $this->seedDepartment('High School');
        $term = $this->seedAcademicCalendar();

        $section = Section::create([
            'academic_year_id' => $term->academic_year_id, 'department_id' => $department->id,
            'name' => 'Grade 10-B', 'capacity' => 30, 'homeroom_teacher_id' => $teacher->id,
        ]);
        $subject = Subject::create(['code' => 'PHY10', 'name' => 'Physics', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $annual = LeaveType::where('name', 'Annual')->first();
        LeaveBalance::create(['staff_id' => $teacher->id, 'leave_type_id' => $annual->id, 'year' => now()->year, 'allocated' => 10, 'used' => 3, 'pending' => 2]);
        LeaveRequest::create([
            'staff_id' => $teacher->id, 'leave_type_id' => $annual->id, 'from_date' => now()->addWeek(), 'to_date' => now()->addWeek()->addDay(),
            'days' => 2, 'reason' => 'Family', 'status' => 'Pending', 'submitted_by' => $teacher->user->id,
        ]);
        StaffAttendance::create(['staff_id' => $teacher->id, 'attendance_date' => now()->startOfMonth(), 'status' => 'Present', 'recorded_by' => $hr->id]);

        $this->actingAs($hr->user)->get(route('hr_office.staff.panel', $teacher))
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('Grade 10-B')
            ->assertSee('Physics')
            ->assertSee('5 of 10 left')
            ->assertSee('1 leave request pending')
            ->assertSee('tel:09-777', false)
            ->assertSee('1 day recorded')
            ->assertSee(route('hr_office.staff.status', $teacher), false);
    }

    public function test_personnel_only_records_have_no_email_action(): void
    {
        $this->seedRoles();
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local');
        $cleaner = $this->makeStaff('teacher', 'Staff', 'cleaner.s-1@internal.yasis.edu');

        $this->actingAs($hr->user)->get(route('hr_office.staff.panel', $cleaner))
            ->assertOk()
            ->assertDontSee('mailto:', false)
            ->assertSee('No (personnel record only)');
    }

    public function test_non_hr_users_cannot_load_the_panel(): void
    {
        $this->seedRoles();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $this->actingAs($teacher->user)->get(route('hr_office.staff.panel', $teacher))->assertForbidden();
    }
}
