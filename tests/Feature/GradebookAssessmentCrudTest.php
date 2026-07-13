<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Inline edit/delete of gradebook items (assessments): a teacher can rename an item and change its
 * max points, and delete an item (cascading its scores). Guards: term-lock, teach-only scoping, and
 * the max-score floor that prevents making an already-entered score exceed the maximum.
 */
class GradebookAssessmentCrudTest extends TestCase
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

        $student = Student::create(['student_id_number' => 'G-0001', 'name' => 'Grade Me', 'admission_date' => now()->subYear(), 'department_id' => $department->id, 'enrollment_status' => 'Enrolled']);
        Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'status' => 'Active']);

        $category = AssessmentCategory::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'term_id' => $term->id, 'name' => 'Quiz', 'weight_pct' => 100]);
        $assessment = Assessment::create(['category_id' => $category->id, 'name' => 'Quiz 1', 'max_score' => 100]);

        return compact('term', 'teacher', 'section', 'subject', 'student', 'category', 'assessment');
    }

    public function test_teacher_can_rename_and_rescale_an_item(): void
    {
        ['teacher' => $teacher, 'assessment' => $assessment] = $this->makeGradebook();

        $this->actingAs($teacher->user)
            ->patch("/teacher/gradebook/assessments/{$assessment->id}", ['name' => 'Pop Quiz', 'max_score' => 25])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'name' => 'Pop Quiz', 'max_score' => 25]);
    }

    public function test_max_score_cannot_drop_below_an_entered_score(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'assessment' => $assessment] = $this->makeGradebook();

        Grade::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 80, 'entered_by' => $teacher->id]);

        $this->actingAs($teacher->user)
            ->patch("/teacher/gradebook/assessments/{$assessment->id}", ['name' => 'Quiz 1', 'max_score' => 50])
            ->assertSessionHasErrors('max_score');

        // Unchanged.
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'max_score' => 100]);
    }

    public function test_teacher_can_delete_an_item_and_its_scores_cascade(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'assessment' => $assessment] = $this->makeGradebook();

        Grade::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 80, 'entered_by' => $teacher->id]);

        $this->actingAs($teacher->user)
            ->delete("/teacher/gradebook/assessments/{$assessment->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseMissing('grades', ['assessment_id' => $assessment->id]);
    }

    public function test_locked_term_blocks_edit_and_delete(): void
    {
        ['term' => $term, 'teacher' => $teacher, 'assessment' => $assessment] = $this->makeGradebook();
        $term->update(['is_locked' => true]);

        $this->actingAs($teacher->user)
            ->patch("/teacher/gradebook/assessments/{$assessment->id}", ['name' => 'X', 'max_score' => 10])
            ->assertSessionHasErrors('term');

        $this->actingAs($teacher->user)
            ->delete("/teacher/gradebook/assessments/{$assessment->id}")
            ->assertSessionHasErrors('term');

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'name' => 'Quiz 1']);
    }

    public function test_teacher_cannot_edit_an_item_in_a_class_they_do_not_teach(): void
    {
        ['assessment' => $assessment] = $this->makeGradebook();
        $intruder = $this->makeStaff('teacher', 'Teacher', 'intruder@test.local');

        $this->actingAs($intruder->user)
            ->patch("/teacher/gradebook/assessments/{$assessment->id}", ['name' => 'Hacked', 'max_score' => 10])
            ->assertForbidden();

        $this->actingAs($intruder->user)
            ->delete("/teacher/gradebook/assessments/{$assessment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'name' => 'Quiz 1']);
    }
}
