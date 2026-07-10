<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * The Principal's governance controls (§3.6) must actually gate the workflows they
 * describe: a locked term is read-only for teachers, a closed promotion window blocks
 * batch preparation, and disabled transcript issuance blocks transcript downloads.
 */
class GovernanceEnforcementTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private function makeGradebook(): array
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $subject = Subject::create(['code' => 'MATH9', 'name' => 'Mathematics', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $student = Student::create(['student_id_number' => 'G-0001', 'first_name' => 'Grade', 'last_name' => 'Me', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

        $category = AssessmentCategory::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'name' => 'Quiz', 'weight_pct' => 100]);
        $assessment = Assessment::create(['category_id' => $category->id, 'name' => 'Quiz 1', 'max_score' => 100]);

        return compact('term', 'teacher', 'section', 'subject', 'student', 'category', 'assessment');
    }

    public function test_teacher_cannot_enter_scores_into_a_locked_term(): void
    {
        ['term' => $term, 'teacher' => $teacher, 'section' => $section, 'subject' => $subject, 'student' => $student, 'assessment' => $assessment] = $this->makeGradebook();

        $term->update(['is_locked' => true]);

        $response = $this->actingAs($teacher->user)->post('/teacher/gradebook/scores', [
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'scores' => [$assessment->id => [$student->id => 95]],
        ]);

        $response->assertSessionHasErrors('term');
        $this->assertDatabaseMissing('grades', ['assessment_id' => $assessment->id, 'student_id' => $student->id]);
    }

    public function test_teacher_can_enter_scores_while_the_term_is_unlocked(): void
    {
        ['term' => $term, 'teacher' => $teacher, 'section' => $section, 'subject' => $subject, 'student' => $student, 'assessment' => $assessment] = $this->makeGradebook();

        $response = $this->actingAs($teacher->user)->post('/teacher/gradebook/scores', [
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'scores' => [$assessment->id => [$student->id => 95]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grades', ['assessment_id' => $assessment->id, 'student_id' => $student->id]);
    }

    public function test_locked_term_blocks_category_and_assessment_changes(): void
    {
        ['term' => $term, 'teacher' => $teacher, 'section' => $section, 'subject' => $subject, 'category' => $category] = $this->makeGradebook();

        $term->update(['is_locked' => true]);

        $this->actingAs($teacher->user)->post('/teacher/gradebook/assessments', [
            'category_id' => $category->id, 'name' => 'Quiz 2', 'max_score' => 100,
        ])->assertSessionHasErrors('term');

        $this->actingAs($teacher->user)->delete("/teacher/gradebook/categories/{$category->id}")
            ->assertSessionHasErrors('term');
        $this->assertDatabaseHas('assessment_categories', ['id' => $category->id]);
    }

    public function test_closed_promotion_window_blocks_batch_preparation(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $term = $this->seedAcademicCalendar();
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');

        $section = Section::create(['academic_year_id' => $term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35]);
        $student = Student::create(['student_id_number' => 'W-0001', 'first_name' => 'Window', 'last_name' => 'Closed', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

        SystemSetting::set('promotion_window_open', '0');

        $this->actingAs($registrar->user)->post("/registrar/promotions/{$section->id}", [
            'actions' => [['student_id' => $student->id, 'action' => 'Graduate']],
        ])->assertForbidden();

        $this->assertDatabaseCount('promotion_batches', 0);
    }

    public function test_disabled_transcript_issuance_blocks_the_download(): void
    {
        $this->seedRoles();
        $department = $this->seedDepartment();
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');

        $student = Student::create(['student_id_number' => 'T-0001', 'first_name' => 'Tran', 'last_name' => 'Script', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);

        $doc = DocumentRequest::create([
            'student_id' => $student->id, 'type' => 'Transcript', 'status' => 'Ready',
            'prepared_by' => $registrar->user->id,
        ]);

        SystemSetting::set('transcript_issuance_enabled', '0');
        $this->actingAs($registrar->user)->get("/registrar/documents/{$doc->id}/download")
            ->assertSessionHasErrors('status');

        SystemSetting::set('transcript_issuance_enabled', '1');
        $this->actingAs($registrar->user)->get("/registrar/documents/{$doc->id}/download")
            ->assertOk();
    }
}
