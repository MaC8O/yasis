<?php

namespace Tests\Feature;

use App\Models\AbsenceNotice;
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
 * §3.6 Module 4: the Registrar may correct an attendance classification after the
 * homeroom teacher has taken attendance — the senior correction path.
 */
class AbsenceCorrectionTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeAbsentRecordWithNotice(): array
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $student = Student::create(['student_id_number' => 'A-0001', 'first_name' => 'Ab', 'last_name' => 'Sent', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

        $guardianUser = User::create(['name' => 'Guardian', 'email' => 'g@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $guardianUser->assignRole('guardian');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'relationship' => 'Mother']);

        $notice = AbsenceNotice::create([
            'student_id' => $student->id, 'guardian_id' => $guardian->id,
            'from_date' => today()->toDateString(), 'to_date' => today()->toDateString(),
            'reason' => 'Family travel', 'status' => 'Submitted',
        ]);

        $record = AttendanceRecord::create([
            'student_id' => $student->id, 'section_id' => $section->id, 'term_id' => $term->id,
            'attendance_date' => today()->toDateString(), 'status' => 'Absent', 'recorded_by' => $teacher->id,
        ]);

        return compact('registrar', 'teacher', 'record', 'notice', 'student');
    }

    public function test_registrar_can_correct_absent_to_excused_and_the_notice_is_linked(): void
    {
        ['registrar' => $registrar, 'record' => $record, 'notice' => $notice] = $this->makeAbsentRecordWithNotice();

        $response = $this->actingAs($registrar->user)
            ->put("/registrar/attendance-corrections/{$record->id}", ['status' => 'Excused']);

        $response->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('Excused', $record->status);
        $this->assertSame($notice->id, $record->absence_notice_id);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $registrar->user->id,
            'action' => 'Corrected absence classification (Absent → Excused)',
            'entity_type' => 'AttendanceRecord',
            'entity_id' => $record->id,
        ]);
    }

    public function test_correcting_away_from_excused_clears_the_notice_link(): void
    {
        ['registrar' => $registrar, 'record' => $record, 'notice' => $notice] = $this->makeAbsentRecordWithNotice();

        $record->update(['status' => 'Excused', 'absence_notice_id' => $notice->id]);

        $this->actingAs($registrar->user)
            ->put("/registrar/attendance-corrections/{$record->id}", ['status' => 'Absent'])
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('Absent', $record->status);
        $this->assertNull($record->absence_notice_id);
    }

    public function test_teacher_cannot_use_the_registrar_correction_path(): void
    {
        ['teacher' => $teacher, 'record' => $record] = $this->makeAbsentRecordWithNotice();

        $this->actingAs($teacher->user)
            ->put("/registrar/attendance-corrections/{$record->id}", ['status' => 'Excused'])
            ->assertForbidden();
    }

    public function test_corrections_page_lists_the_mismatched_record_under_needs_review(): void
    {
        ['registrar' => $registrar, 'student' => $student] = $this->makeAbsentRecordWithNotice();

        $this->actingAs($registrar->user)->get('/registrar/attendance-corrections')
            ->assertOk()
            ->assertSee('Needs review')
            ->assertSee($student->first_name);
    }
}
