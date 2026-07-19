<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

class AuditEnrichmentTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    public function test_grade_entry_audit_captures_ip_and_before_after_values(): void
    {
        $this->seedRoles();
        $term = $this->seedAcademicCalendar();
        $department = $this->seedDepartment();
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $section = Section::create([
            'academic_year_id' => $term->academic_year_id,
            'department_id' => $department->id,
            'name' => 'Grade 9-A',
            'capacity' => 35,
        ]);
        $subject = Subject::create(['code' => 'MATH9', 'name' => 'Mathematics', 'department_id' => $department->id]);
        $student = Student::create([
            'student_id_number' => 'YAS-0001',
            'name' => 'Aye Chan',
            'admission_date' => now()->subYear(),
            'department_id' => $department->id,
            'enrollment_status' => 'Enrolled',
        ]);
        $category = AssessmentCategory::create([
            'section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'name' => 'Quiz', 'weight_pct' => 100,
        ]);
        $assessment = Assessment::create(['category_id' => $category->id, 'name' => 'Quiz 1', 'max_score' => 100]);

        $payload = [
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'scores' => [$assessment->id => [$student->id => 90]],
        ];

        // First entry: a brand-new grade — recorded as null → 90.
        $this->actingAs($teacher->user)->post(route('teacher.gradebook.scores.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(90.0, (float) Grade::where('assessment_id', $assessment->id)->where('student_id', $student->id)->value('score'));

        $log = \App\Models\AuditLog::where('action', 'Entered grades')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertNotNull($log->user_agent);
        $this->assertCount(1, $log->details['changes']);
        $this->assertNull($log->details['changes'][0]['from']);
        $this->assertSame(90.0, (float) $log->details['changes'][0]['to']);

        // Second entry: an edit — recorded as 90 → 95.
        $payload['scores'][$assessment->id][$student->id] = 95;
        $this->actingAs($teacher->user)->post(route('teacher.gradebook.scores.store'), $payload)
            ->assertSessionHasNoErrors();

        $edit = \App\Models\AuditLog::where('action', 'Entered grades')->latest('id')->first();
        $this->assertSame(90.0, (float) $edit->details['changes'][0]['from']);
        $this->assertSame(95.0, (float) $edit->details['changes'][0]['to']);
    }

    public function test_unchanged_scores_produce_no_change_detail(): void
    {
        $this->seedRoles();
        $term = $this->seedAcademicCalendar();
        $department = $this->seedDepartment();
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => '9-A', 'capacity' => 35]);
        $subject = Subject::create(['code' => 'ENG9', 'name' => 'English', 'department_id' => $department->id]);
        $student = Student::create(['student_id_number' => 'YAS-0002', 'name' => 'Su Su', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        $category = AssessmentCategory::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'name' => 'Test', 'weight_pct' => 100]);
        $assessment = Assessment::create(['category_id' => $category->id, 'name' => 'Test 1', 'max_score' => 100]);
        Grade::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 80, 'entered_by' => $teacher->id]);

        // Re-submitting the same score changes nothing, so no change detail is recorded.
        $this->actingAs($teacher->user)->post(route('teacher.gradebook.scores.store'), [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'scores' => [$assessment->id => [$student->id => 80]],
        ])->assertSessionHasNoErrors();

        $log = \App\Models\AuditLog::where('action', 'Entered grades')->latest('id')->first();
        $this->assertNull($log->details);
    }

    public function test_audit_viewer_shows_ip_and_change_disclosure(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        \App\Models\AuditLog::create([
            'user_id' => $admin->user->id, 'role' => 'admin', 'ip_address' => '198.51.100.7',
            'action' => 'Entered grades', 'entity_type' => 'Section', 'entity_id' => 3,
            'details' => ['changes' => [['assessment_id' => 1, 'student_id' => 2, 'from' => 70, 'to' => 88]]],
            'created_at' => now(),
        ]);

        $this->actingAs($admin->user)->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('198.51.100.7')
            ->assertSee('1 value change(s)')
            ->assertSee('70 → 88');
    }

    public function test_non_http_audit_leaves_ip_null(): void
    {
        $this->seedRoles();
        $admin = $this->makeStaff('admin', 'Admin');

        // Called directly (no routed HTTP request) — mirrors a console command / queued job.
        app(AuditService::class)->log($admin->user, 'Console-initiated action', 'System');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Console-initiated action',
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}
