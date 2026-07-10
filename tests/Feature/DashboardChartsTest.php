<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Dashboards render their chart cards from live data (and degrade to empty
 * states when there is none).
 */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_admin_dashboard_renders_login_trend_and_role_chart(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        AuditLog::create([
            'user_id' => $admin->user->id, 'role' => 'admin', 'action' => 'Logged in',
            'entity_type' => 'User', 'entity_id' => $admin->user->id, 'created_at' => now(),
        ]);

        $this->actingAs($admin->user)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Sign-ins — last 14 days')
            ->assertSee('Accounts by role')
            ->assertSee('<svg', false);
    }

    public function test_principal_dashboard_renders_enrollment_attendance_and_fee_charts(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $principal = $this->makeStaff('principal', 'Principal');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 30]);
        $student = Student::create([
            'student_id_number' => 'S-1', 'first_name' => 'Aye', 'last_name' => 'Min',
            'date_of_birth' => now()->subYears(14), 'gender' => 'F',
            'department_id' => $department->id, 'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
        ]);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);
        AttendanceRecord::create([
            'student_id' => $student->id, 'section_id' => $section->id, 'term_id' => $term->id,
            'attendance_date' => today()->toDateString(), 'status' => 'Present', 'recorded_by' => $teacher->id,
        ]);

        $this->actingAs($principal->user)->get('/principal/dashboard')
            ->assertOk()
            ->assertSee('Enrollment by department')
            ->assertSee('Attendance — recent school days')
            ->assertSee('Fee collection status');
    }

    public function test_teacher_dashboard_renders_section_attendance_chart(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 30, 'homeroom_teacher_id' => $teacher->id]);
        $subject = Subject::create(['code' => 'MAT9', 'name' => 'Math', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $student = Student::create([
            'student_id_number' => 'S-2', 'first_name' => 'Htoo', 'last_name' => 'Paw',
            'date_of_birth' => now()->subYears(14), 'gender' => 'M',
            'department_id' => $department->id, 'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear(),
        ]);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);
        AttendanceRecord::create([
            'student_id' => $student->id, 'section_id' => $section->id, 'term_id' => $term->id,
            'attendance_date' => today()->subDay()->toDateString(), 'status' => 'Present', 'recorded_by' => $teacher->id,
        ]);

        $this->actingAs($teacher->user)->get('/teacher/dashboard')
            ->assertOk()
            ->assertSee('Attendance rate — my classes');
    }

    public function test_hr_and_registrar_and_treasurer_dashboards_render_with_no_data(): void
    {
        $this->seedRoles();
        $this->seedLeaveTypes();
        $hr = $this->makeStaff('hr_office', 'HR_Office');
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        $treasurer = $this->makeStaff('treasurer', 'Treasurer', 'treasurer@test.local');

        $this->actingAs($hr->user)->get('/hr_office/dashboard')->assertOk()->assertSee('Leave requests');
        $this->actingAs($registrar->user)->get('/registrar/dashboard')->assertOk()->assertSee('Enrollment by class');
        $this->actingAs($treasurer->user)->get('/treasurer/dashboard')->assertOk()->assertSee('Collection rate by period');
    }
}
