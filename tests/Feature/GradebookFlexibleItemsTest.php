<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Teacher gradebook: typed gradable items (assignment/classwork/group work/test/quiz) under
 * weighted categories, fast one-item whole-class score entry, and write-side scoping.
 */
class GradebookFlexibleItemsTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $teacher;

    private StaffProfile $otherTeacher;

    private Section $section;

    private Subject $subject;

    private Term $term;

    private AssessmentCategory $quizzes;

    private AssessmentCategory $tests;

    /** @var list<Student> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $department = $this->seedDepartment();
        $this->term = $this->seedAcademicCalendar();
        $this->teacher = $this->makeStaff('teacher', 'Teacher', 'teacher.a@test.local');
        $this->otherTeacher = $this->makeStaff('teacher', 'Teacher', 'teacher.b@test.local');
        $this->section = Section::create(['academic_year_id' => $this->term->academic_year_id, 'department_id' => $department->id, 'name' => 'Grade 9-A', 'capacity' => 35, 'homeroom_teacher_id' => $this->teacher->id]);
        $this->subject = Subject::create(['code' => 'MATH9', 'name' => 'Mathematics', 'department_id' => $department->id]);
        TeachingAssignment::create(['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id]);

        foreach (['Aye Min', 'Htoo Paw', 'Su Su'] as $i => $name) {
            $student = Student::create(['student_id_number' => 'S-10'.$i, 'name' => $name, 'date_of_birth' => now()->subYears(14), 'gender' => 'F',
                'department_id' => $department->id, 'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear()]);
            Enrollment::create(['student_id' => $student->id, 'section_id' => $this->section->id, 'status' => 'Active']);
            $this->students[] = $student;
        }

        $this->quizzes = AssessmentCategory::create(['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id, 'name' => 'Quizzes', 'weight_pct' => 40]);
        $this->tests = AssessmentCategory::create(['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id, 'name' => 'Tests', 'weight_pct' => 60]);
    }

    private function asTeacher()
    {
        return $this->actingAs($this->teacher->user);
    }

    public function test_teacher_can_add_any_number_of_typed_items_to_a_category(): void
    {
        foreach (['assignment', 'classwork', 'group_work', 'test', 'quiz', 'quiz', 'quiz'] as $i => $type) {
            $this->asTeacher()->post('/teacher/gradebook/assessments', ['category_id' => $this->quizzes->id, 'name' => "Item {$i}", 'type' => $type, 'max_score' => 20])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(7, Assessment::where('category_id', $this->quizzes->id)->count());
        $this->assertSame(3, Assessment::where('type', 'quiz')->count());
        $this->assertDatabaseHas('assessments', ['name' => 'Item 2', 'type' => 'group_work', 'max_score' => 20]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Created assessment', 'role' => 'teacher']);
    }

    public function test_type_is_inferred_from_the_name_when_omitted_and_invalid_types_are_rejected(): void
    {
        $this->asTeacher()->post('/teacher/gradebook/assessments', ['category_id' => $this->quizzes->id, 'name' => 'Quiz 2', 'max_score' => 10])->assertSessionHasNoErrors();
        $this->asTeacher()->post('/teacher/gradebook/assessments', ['category_id' => $this->tests->id, 'name' => 'Midterm Exam', 'max_score' => 50])->assertSessionHasNoErrors();
        $this->asTeacher()->post('/teacher/gradebook/assessments', ['category_id' => $this->tests->id, 'name' => 'Essay', 'max_score' => 50])->assertSessionHasNoErrors();
        $this->asTeacher()->post('/teacher/gradebook/assessments', ['category_id' => $this->tests->id, 'name' => 'Bad', 'type' => 'homework', 'max_score' => 50])
            ->assertSessionHasErrors('type');

        $this->assertSame('quiz', Assessment::where('name', 'Quiz 2')->value('type'));
        $this->assertSame('test', Assessment::where('name', 'Midterm Exam')->value('type'));
        $this->assertSame('assignment', Assessment::where('name', 'Essay')->value('type'));
        $this->assertNull(Assessment::where('name', 'Bad')->first());
    }

    public function test_editing_an_item_can_change_its_type_and_keeps_it_when_not_sent(): void
    {
        $item = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Pop quiz', 'max_score' => 10]);

        $this->asTeacher()->patch("/teacher/gradebook/assessments/{$item->id}", ['name' => 'Pop quiz', 'type' => 'classwork', 'max_score' => 10])->assertSessionHasNoErrors();
        $this->assertSame('classwork', $item->fresh()->type);

        $this->asTeacher()->patch("/teacher/gradebook/assessments/{$item->id}", ['name' => 'Pop quiz 1', 'max_score' => 12])->assertSessionHasNoErrors();
        $this->assertSame('classwork', $item->fresh()->type);
    }

    public function test_item_score_page_lists_the_whole_class_with_neighbour_navigation(): void
    {
        $first = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 1', 'max_score' => 20]);
        $second = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 2', 'max_score' => 20]);
        Grade::create(['assessment_id' => $first->id, 'student_id' => $this->students[0]->id, 'score' => 17, 'entered_by' => $this->teacher->id]);

        $this->asTeacher()->get("/teacher/gradebook/assessments/{$first->id}/scores")
            ->assertOk()
            ->assertSee('Aye Min')->assertSee('Htoo Paw')->assertSee('Su Su')
            ->assertSee('Out of 20')->assertSee('Quiz')
            ->assertSee('value="17"', false)
            ->assertSee(route('teacher.gradebook.assessments.scores', $second), false);
    }

    public function test_whole_class_scores_save_in_one_request_and_feed_the_unchanged_weighted_result(): void
    {
        $quiz = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 1', 'max_score' => 20]);
        $test = Assessment::create(['category_id' => $this->tests->id, 'name' => 'Term Test', 'max_score' => 100]);
        [$a, $b, $c] = $this->students;

        $this->asTeacher()->put("/teacher/gradebook/assessments/{$quiz->id}/scores", ['scores' => [$a->id => 18, $b->id => 10, $c->id => '']])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Saved 2 scores for Quiz 1 (2 changed).');
        $this->asTeacher()->put("/teacher/gradebook/assessments/{$test->id}/scores", ['scores' => [$a->id => 85]])->assertSessionHasNoErrors();

        $this->assertSame(3, Grade::count());
        $this->assertNull(Grade::where('student_id', $c->id)->first(), 'blank boxes are skipped');
        $log = AuditLog::where('action', 'Entered scores for item "Quiz 1"')->firstOrFail();
        $this->assertSame('teacher', $log->role);
        $this->assertCount(2, $log->details['changes']);

        // Weighted result is unchanged by item types: 0.4 * 90% + 0.6 * 85% = 87%.
        $marks = app(GradeService::class)->sectionMarks($this->section, $this->term);
        $this->assertSame('87.00', $marks[$a->id][$this->subject->id]);
    }

    public function test_a_score_above_the_maximum_is_rejected_and_nothing_in_that_save_is_stored(): void
    {
        $quiz = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 1', 'max_score' => 20]);
        [$a, $b] = $this->students;

        $this->asTeacher()->put("/teacher/gradebook/assessments/{$quiz->id}/scores", ['scores' => [$a->id => 15, $b->id => 25]])
            ->assertSessionHasErrors("scores.{$quiz->id}.{$b->id}");
        $this->assertSame(0, Grade::count());

        // The full grid enforces the same maximum.
        $this->asTeacher()->post('/teacher/gradebook/scores', ['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id,
            'scores' => [$quiz->id => [$a->id => 21]]])->assertSessionHasErrors();
        $this->assertSame(0, Grade::count());
    }

    public function test_scores_for_students_outside_the_roster_are_rejected(): void
    {
        $quiz = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 1', 'max_score' => 20]);
        $outsider = Student::create(['student_id_number' => 'S-999', 'name' => 'Not In Class', 'date_of_birth' => now()->subYears(14), 'gender' => 'M',
            'department_id' => $this->section->department_id, 'enrollment_status' => 'Enrolled', 'admission_date' => now()->subYear()]);

        $this->asTeacher()->put("/teacher/gradebook/assessments/{$quiz->id}/scores", ['scores' => [$outsider->id => 10]])->assertSessionHasErrors();
        $this->assertSame(0, Grade::count());
    }

    public function test_locked_term_item_scores_are_read_only(): void
    {
        $quiz = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 1', 'max_score' => 20]);
        $this->term->update(['is_locked' => true]);

        $this->asTeacher()->get("/teacher/gradebook/assessments/{$quiz->id}/scores")->assertOk()->assertSee('is locked by the Principal');
        $this->asTeacher()->put("/teacher/gradebook/assessments/{$quiz->id}/scores", ['scores' => [$this->students[0]->id => 10]])
            ->assertSessionHasErrors('term');
        $this->assertSame(0, Grade::count());
    }

    public function test_teachers_cannot_read_or_write_another_class_gradebook(): void
    {
        $quiz = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 1', 'max_score' => 20]);
        $other = $this->actingAs($this->otherTeacher->user);

        $other->get("/teacher/gradebook/assessments/{$quiz->id}/scores")->assertForbidden();
        $other->put("/teacher/gradebook/assessments/{$quiz->id}/scores", ['scores' => [$this->students[0]->id => 10]])->assertForbidden();
        $other->post('/teacher/gradebook/categories', ['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id, 'name' => 'Intrusion', 'weight_pct' => 1])->assertForbidden();
        $other->post('/teacher/gradebook/assessments', ['category_id' => $this->quizzes->id, 'name' => 'Intrusion', 'max_score' => 10])->assertForbidden();
        $other->delete("/teacher/gradebook/categories/{$this->quizzes->id}")->assertForbidden();
        $other->post('/teacher/gradebook/scores', ['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id,
            'scores' => [$quiz->id => [$this->students[0]->id => 10]]])->assertForbidden();
        $other->post('/teacher/gradebook/comments', ['student_id' => $this->students[0]->id, 'term_id' => $this->term->id, 'comment' => 'x'])->assertForbidden();

        $this->assertSame(0, Grade::count());
        $this->assertNull(AssessmentCategory::where('name', 'Intrusion')->first());
        $this->assertNull(Assessment::where('name', 'Intrusion')->first());
        $this->assertNotNull($this->quizzes->fresh());
    }

    public function test_an_assessment_from_another_section_cannot_be_slipped_into_a_grid_save(): void
    {
        // Same teacher, but the item belongs to a different section they don't teach.
        $foreignSection = Section::create(['academic_year_id' => $this->term->academic_year_id, 'department_id' => $this->section->department_id, 'name' => 'Grade 9-B', 'capacity' => 35]);
        $foreignCategory = AssessmentCategory::create(['section_id' => $foreignSection->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id, 'name' => 'Quizzes', 'weight_pct' => 100]);
        $foreignItem = Assessment::create(['category_id' => $foreignCategory->id, 'name' => 'Quiz 1', 'max_score' => 20]);

        $this->asTeacher()->post('/teacher/gradebook/scores', ['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id,
            'scores' => [$foreignItem->id => [$this->students[0]->id => 10]]])->assertForbidden();
        $this->assertSame(0, Grade::count());
    }

    public function test_category_weights_still_cannot_exceed_100_percent(): void
    {
        $this->asTeacher()->post('/teacher/gradebook/categories', ['section_id' => $this->section->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id, 'name' => 'Group work', 'weight_pct' => 5])
            ->assertSessionHasErrors('weight_pct');
        $this->assertSame(2, AssessmentCategory::count());
    }

    public function test_gradebook_page_groups_items_by_category_with_types_and_entry_links(): void
    {
        $quiz = Assessment::create(['category_id' => $this->quizzes->id, 'name' => 'Quiz 1', 'type' => 'quiz', 'max_score' => 20]);
        Assessment::create(['category_id' => $this->tests->id, 'name' => 'Group project', 'type' => 'group_work', 'max_score' => 50]);
        Grade::create(['assessment_id' => $quiz->id, 'student_id' => $this->students[0]->id, 'score' => 17, 'entered_by' => $this->teacher->id]);

        $this->asTeacher()->get("/teacher/gradebook?term={$this->term->id}")
            ->assertOk()
            ->assertSee('Expand all')->assertSee('Group work')
            ->assertSee('scored 1/3')
            ->assertSee(route('teacher.gradebook.assessments.scores', $quiz), false);
    }
}
