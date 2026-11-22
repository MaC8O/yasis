<?php

namespace Tests\Feature\Behavior;

use App\Models\AcademicYear;
use App\Models\BehaviorReport;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\Behavior\BehaviorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsBehaviorFixtures;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/** Who can see which student's behavior record. */
class BehaviorVisibilityTest extends TestCase
{
    use BuildsBehaviorFixtures, RefreshDatabase, SeedsCoreData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchool();
    }

    private function strangerStudent(): Student
    {
        // Enrolled in another section of the active year that the teacher neither teaches nor homerooms.
        $section = Section::create(['academic_year_id' => $this->year->id, 'department_id' => $this->section->department_id, 'name' => 'Grade 10-B', 'capacity' => 30]);
        $student = Student::create(['student_id_number' => 'S-STRANGER', 'name' => 'Never Taught', 'admission_date' => now()->subYear(), 'department_id' => $this->section->department_id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

        return $student;
    }

    public function test_vp_principal_admin_and_registrar_see_every_student(): void
    {
        $stranger = $this->strangerStudent();

        foreach ([$this->vp, $this->principal, $this->admin, $this->registrar] as $user) {
            $this->actingAs($user)->get(route('behavior.students.show', $stranger))->assertOk();
            $this->actingAs($user)->get(route('behavior.index'))->assertOk()->assertSee('Never Taught');
        }
    }

    public function test_teacher_sees_students_in_sections_they_teach_this_year(): void
    {
        $this->actingAs($this->teacher)->get(route('behavior.students.show', $this->student))->assertOk();
        $this->actingAs($this->teacher)->get(route('behavior.index'))->assertOk()->assertSee($this->student->name);
    }

    public function test_homeroom_teacher_sees_their_homeroom_students(): void
    {
        $this->section->update(['homeroom_teacher_id' => $this->otherTeacher->id]);

        $this->actingAs($this->otherTeacher)->get(route('behavior.students.show', $this->student))->assertOk();
    }

    public function test_teacher_opening_a_student_they_have_never_taught_gets_403(): void
    {
        $stranger = $this->strangerStudent();

        $this->actingAs($this->teacher)->get(route('behavior.students.show', $stranger))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('behavior.index'))->assertDontSee('Never Taught');
        $this->actingAs($this->teacher)->get(route('behavior.reports.create', $stranger))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('behavior.reports.store', $stranger), $this->reportFields())->assertForbidden();
        $this->assertSame(0, BehaviorReport::count());
    }

    public function test_teacher_opening_a_student_they_taught_only_in_a_previous_year_gets_403(): void
    {
        // Last year: the teacher taught this student. This year: the student is in a section they don't teach.
        $lastYear = AcademicYear::create(['year_label' => 'Last year', 'is_active' => false]);
        $oldSection = Section::create(['academic_year_id' => $lastYear->id, 'department_id' => $this->section->department_id, 'name' => 'Grade 8-A', 'capacity' => 30]);
        $subject = Subject::create(['code' => 'ENG8', 'name' => 'English 8', 'department_id' => $this->section->department_id]);
        TeachingAssignment::create(['section_id' => $oldSection->id, 'subject_id' => $subject->id, 'teacher_id' => $this->otherTeacher->id]);
        Enrollment::create(['student_id' => $this->student->id, 'section_id' => $oldSection->id, 'status' => 'Active']);

        $this->actingAs($this->otherTeacher)->get(route('behavior.students.show', $this->student))->assertForbidden();
        $this->actingAs($this->otherTeacher)->get(route('behavior.index'))->assertDontSee($this->student->name);
    }

    public function test_teacher_keeps_their_own_report_but_not_the_record_once_the_student_leaves_their_scope(): void
    {
        $report = app(BehaviorService::class)->submitReport($this->teacher, $this->student, $this->reportFields());
        Enrollment::where('student_id', $this->student->id)->update(['status' => 'Withdrawn']);

        $this->actingAs($this->teacher)->get(route('behavior.students.show', $this->student))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('behavior.reports.show', $report))->assertOk()
            ->assertSee('no longer in your classes')
            ->assertDontSee('Behavior score');
        $this->actingAs($this->teacher)->get(route('behavior.index'))->assertSee($this->student->name); // in "My reports"

        // ...and can still edit or withdraw it while it is Submitted.
        $this->actingAs($this->teacher)->post(route('behavior.reports.void', $report), ['reason' => 'Logged against the wrong student'])->assertRedirect();
        $this->assertSame('Voided', $report->fresh()->status);
    }

    public function test_another_teachers_report_is_not_reachable_out_of_scope(): void
    {
        $report = app(BehaviorService::class)->submitReport($this->teacher, $this->student, $this->reportFields());
        $stranger = $this->makeStaff('teacher', 'Teacher', 'teacher3@test.local')->user;

        $this->actingAs($stranger)->get(route('behavior.reports.show', $report))->assertForbidden();
    }

    public function test_student_sees_only_their_own_record(): void
    {
        $this->actingAs($this->studentUser)->get(route('student.behavior.index'))->assertOk()->assertSee('Your behavior record');
        $this->actingAs($this->studentUser)->get(route('behavior.students.show', $this->student))->assertForbidden();
        $this->actingAs($this->studentUser)->get(route('behavior.students.show', $this->otherStudent))->assertForbidden();
    }

    public function test_guardian_sees_own_child_and_gets_403_for_another_familys_child(): void
    {
        $this->actingAs($this->guardianUser)->get(route('guardian.behavior.index', ['child' => $this->student->id]))->assertOk()->assertSee($this->student->name);
        $this->actingAs($this->guardianUser)->get(route('guardian.behavior.index', ['child' => $this->otherStudent->id]))->assertForbidden();
        $this->actingAs($this->guardianUser)->get(route('behavior.students.show', $this->otherStudent))->assertForbidden();
    }

    public function test_roles_without_behavior_access_are_refused(): void
    {
        $treasurer = $this->makeStaff('treasurer', 'Treasurer', 'treasurer@test.local')->user;
        $hr = $this->makeStaff('hr_office', 'HR_Office', 'hr@test.local')->user;

        foreach ([$treasurer, $hr] as $user) {
            $this->actingAs($user)->get(route('behavior.index'))->assertForbidden();
            $this->actingAs($user)->get(route('behavior.students.show', $this->student))->assertForbidden();
        }
        $this->actingAs($this->studentUser)->get(route('behavior.index'))->assertForbidden();
        $this->actingAs($this->guardianUser)->get(route('behavior.index'))->assertForbidden();
    }

    public function test_cases_list_is_for_vp_principal_admin_and_registrar_only(): void
    {
        $this->actingAs($this->teacher)->get(route('behavior.cases.index'))->assertForbidden();
        foreach ([$this->vp, $this->principal, $this->admin, $this->registrar] as $user) {
            $this->actingAs($user)->get(route('behavior.cases.index'))->assertOk();
        }
    }
}
