<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Exercises §3.6 Module 4: a guardian's advance absence notice is a notification,
 * not an approval — it flags the roster, and the homeroom teacher's Excused status
 * is applied only when attendance is actually taken, referencing the notice.
 */
class AbsenceExcusedFlowTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_guardian_notice_defaults_the_students_attendance_to_excused_when_teacher_takes_it(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local', ['department_id' => $department->id]);

        $section = Section::create([
            'academic_year_id' => $term->academic_year_id,
            'department_id' => $department->id,
            'name' => 'Grade 9-A',
            'homeroom_teacher_id' => $teacher->id,
            'capacity' => 35,
        ]);

        $student = Student::create([
            'student_id_number' => 'T-STU-0001',
            'first_name' => 'Test', 'last_name' => 'Student',
            'admission_date' => now()->subYear(),
            'department_id' => $department->id,
            'enrollment_status' => 'Enrolled',
        ]);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

        $guardianUser = User::create(['name' => 'Test Guardian', 'email' => 'guardian@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $guardianUser->assignRole('guardian');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother']);
        $guardian->students()->attach($student->id, ['is_primary' => true]);

        // Guardian submits the advance absence notice for today.
        $this->actingAs($guardianUser)->post('/guardian/absence-notices', [
            'student_id' => $student->id,
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
            'reason' => 'Medical appointment',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('absence_notices', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'status' => 'Submitted',
        ]);

        // The teacher's attendance sheet for today defaults this student to Excused.
        $attendancePage = $this->actingAs($teacher->user)
            ->get("/teacher/attendance?section={$section->id}&date=".today()->toDateString());
        $attendancePage->assertOk();
        $attendancePage->assertSee('Excused notice on file');

        // Teacher saves attendance, accepting the Excused default.
        $noticeId = \App\Models\AbsenceNotice::where('student_id', $student->id)->first()->id;
        $this->actingAs($teacher->user)->post('/teacher/attendance', [
            'section_id' => $section->id,
            'attendance_date' => today()->toDateString(),
            'term_id' => $term->id,
            'statuses' => [
                ['student_id' => $student->id, 'status' => 'Excused', 'remark' => 'Excused notice on file'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'section_id' => $section->id,
            'status' => 'Excused',
            'absence_notice_id' => $noticeId,
        ]);
    }

    public function test_guardian_can_cancel_an_upcoming_notice_but_not_a_past_one(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $student = Student::create([
            'student_id_number' => 'T-STU-0002', 'first_name' => 'Past', 'last_name' => 'Notice',
            'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled',
        ]);
        $guardianUser = User::create(['name' => 'G2', 'email' => 'g2@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $guardianUser->assignRole('guardian');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Father']);
        $guardian->students()->attach($student->id, ['is_primary' => true]);

        $upcoming = \App\Models\AbsenceNotice::create([
            'student_id' => $student->id, 'guardian_id' => $guardian->id,
            'from_date' => today()->addDays(3), 'to_date' => today()->addDays(3),
            'status' => 'Submitted',
        ]);
        $past = \App\Models\AbsenceNotice::create([
            'student_id' => $student->id, 'guardian_id' => $guardian->id,
            'from_date' => today()->subDays(3), 'to_date' => today()->subDays(3),
            'status' => 'Submitted',
        ]);

        $this->actingAs($guardianUser)->post("/guardian/absence-notices/{$upcoming->id}/cancel")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('absence_notices', ['id' => $upcoming->id, 'status' => 'Cancelled']);

        $this->actingAs($guardianUser)->post("/guardian/absence-notices/{$past->id}/cancel")
            ->assertForbidden();
        $this->assertDatabaseHas('absence_notices', ['id' => $past->id, 'status' => 'Submitted']);
    }
}
