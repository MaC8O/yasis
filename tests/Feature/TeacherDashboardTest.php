<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_dashboard_flags_students_with_three_consecutive_absences(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $subject = Subject::create(['code' => 'MATH9', 'name' => 'Mathematics', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $chronic = Student::create(['student_id_number' => 'C-0001', 'first_name' => 'Chronic', 'last_name' => 'Absentee', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        $regular = Student::create(['student_id_number' => 'C-0002', 'first_name' => 'Regular', 'last_name' => 'Attender', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $chronic->id, 'section_id' => $section->id, 'status' => 'Active']);
        Enrollment::create(['student_id' => $regular->id, 'section_id' => $section->id, 'status' => 'Active']);

        foreach ([2, 1, 0] as $daysAgo) {
            AttendanceRecord::create([
                'student_id' => $chronic->id, 'section_id' => $section->id, 'term_id' => $term->id,
                'attendance_date' => today()->subDays($daysAgo)->toDateString(), 'status' => 'Absent', 'recorded_by' => $teacher->id,
            ]);
            AttendanceRecord::create([
                'student_id' => $regular->id, 'section_id' => $section->id, 'term_id' => $term->id,
                'attendance_date' => today()->subDays($daysAgo)->toDateString(), 'status' => 'Present', 'recorded_by' => $teacher->id,
            ]);
        }

        $response = $this->actingAs($teacher->user)->get('/teacher/dashboard');

        $response->assertOk()
            ->assertSee('Consecutive absences')
            ->assertSee('Chronic Absentee')
            ->assertDontSee('Regular Attender');
    }
}
