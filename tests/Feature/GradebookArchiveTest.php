<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\GradingScheme;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\GradebookTemplateService;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Removing a gradebook item, or a teacher's own category (Grades 11-12), archives it: its grades
 * are kept, it counts in no mark, and the teacher can restore it while the term is open. Locked
 * terms stay read-only. Both actions are audited.
 */
class GradebookArchiveTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $teacher;

    private Section $eleven;

    private Student $student;

    private int $math;

    private AssessmentCategory $coursework;

    private AssessmentCategory $exams;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $this->math = Subject::where('code', 'MATH')->whereNull('department_id')->value('id');
        // Since 9b every grade uses the template; a scheme that doesn't (data the Admin can't change
        // but a migration could) still lets teachers keep their own categories, archived by these rules.
        GradingScheme::where('code', 'G11_12')->update(['uses_gradebook_template' => false]);
        $this->eleven = $this->levelSection('Grade 11-A', '11');
        TeachingAssignment::create(['section_id' => $this->eleven->id, 'subject_id' => $this->math, 'teacher_id' => $this->teacher->id]);
        $this->student = $this->pupil('Eleven Student', $this->eleven);

        $base = ['section_id' => $this->eleven->id, 'subject_id' => $this->math, 'term_id' => $this->terms[0]->id];
        $this->coursework = AssessmentCategory::create($base + ['name' => 'Coursework', 'weight_pct' => 40]);
        $this->exams = AssessmentCategory::create($base + ['name' => 'Exams', 'weight_pct' => 60]);
    }

    private function item(AssessmentCategory $category, string $name, int $score): Assessment
    {
        $item = Assessment::create(['category_id' => $category->id, 'name' => $name, 'max_score' => 100]);
        Grade::create(['assessment_id' => $item->id, 'student_id' => $this->student->id, 'score' => $score, 'entered_by' => $this->teacher->id]);

        return $item;
    }

    private function mark(): ?string
    {
        return app(GradeService::class)->sectionMarks($this->eleven, $this->terms[0])[$this->student->id][$this->math] ?? null;
    }

    private function as()
    {
        return $this->actingAs($this->teacher->user);
    }

    public function test_an_archived_item_keeps_its_grades_and_stops_counting_until_restored(): void
    {
        $this->item($this->coursework, 'Essay', 90);
        $this->item($this->coursework, 'Poster', 50);
        $this->item($this->exams, 'Final', 70);
        // Coursework mean (90 + 50) / 2 = 70; (70 x 40 + 70 x 60) / 100 = 70.
        $this->assertSame('70.00', $this->mark());

        $poster = Assessment::where('name', 'Poster')->sole();
        $this->as()->delete("/teacher/gradebook/assessments/{$poster->id}")->assertSessionHas('status');

        $this->assertNotNull($poster->fresh()->archived_at);
        $this->assertSame('50.00', (string) Grade::where('assessment_id', $poster->id)->value('score'));
        // Coursework is now Essay alone (90): (90 x 40 + 70 x 60) / 100 = 78.
        $this->assertSame('78.00', $this->mark());
        $log = AuditLog::where('action', 'Archived assessment')->sole();
        $this->assertSame(['teacher', 1], [$log->role, $log->details['grades']]);

        // No new scores or edits while archived; it's listed for restoring.
        $this->as()->put("/teacher/gradebook/assessments/{$poster->id}/scores", ['scores' => [$this->student->id => 99]])->assertSessionHas('warning');
        $this->assertSame('50.00', (string) Grade::where('assessment_id', $poster->id)->value('score'));
        $this->as()->get("/teacher/gradebook?section={$this->eleven->id}&subject={$this->math}&term={$this->terms[0]->id}")->assertOk()
            ->assertSee('Archived items')->assertSee('Poster');

        $this->as()->post("/teacher/gradebook/assessments/{$poster->id}/restore")->assertSessionHas('status');
        $this->assertNull($poster->fresh()->archived_at);
        $this->assertSame('70.00', $this->mark());
        $this->assertSame(1, AuditLog::where('action', 'Restored assessment')->count());
    }

    public function test_an_archived_own_category_keeps_its_grades_and_stops_counting_until_restored(): void
    {
        $essay = $this->item($this->coursework, 'Essay', 90);
        $this->item($this->exams, 'Final', 70);
        $this->assertSame('78.00', $this->mark());

        $this->as()->delete("/teacher/gradebook/categories/{$this->coursework->id}")->assertSessionHas('status');

        $this->assertNotNull($this->coursework->fresh()->archived_at);
        $this->assertSame(1, Assessment::where('category_id', $this->coursework->id)->count());
        $this->assertSame('90.00', (string) Grade::where('assessment_id', $essay->id)->value('score'));
        // Only Exams counts now.
        $this->assertSame('70.00', $this->mark());
        $log = AuditLog::where('action', 'Archived assessment category')->sole();
        $this->assertSame([1, 1], [$log->details['items'], $log->details['grades']]);

        $this->as()->post("/teacher/gradebook/categories/{$this->coursework->id}/restore")->assertSessionHas('status');
        $this->assertNull($this->coursework->fresh()->archived_at);
        $this->assertSame('78.00', $this->mark());
        $this->assertSame(1, AuditLog::where('action', 'Restored assessment category')->count());
    }

    public function test_restoring_a_category_refuses_to_take_the_weights_above_100(): void
    {
        $this->as()->delete("/teacher/gradebook/categories/{$this->coursework->id}");
        AssessmentCategory::create(['section_id' => $this->eleven->id, 'subject_id' => $this->math, 'term_id' => $this->terms[0]->id, 'name' => 'Projects', 'weight_pct' => 40]);

        $this->as()->post("/teacher/gradebook/categories/{$this->coursework->id}/restore")->assertSessionHas('warning');
        $this->assertNotNull($this->coursework->fresh()->archived_at);
    }

    public function test_a_locked_term_stays_read_only(): void
    {
        $item = $this->item($this->coursework, 'Essay', 90);
        $archived = $this->item($this->exams, 'Old quiz', 60);
        $archived->update(['archived_at' => now()]);
        $this->terms[0]->update(['is_locked' => true]);

        $this->as()->delete("/teacher/gradebook/assessments/{$item->id}")->assertSessionHasErrors('term');
        $this->as()->delete("/teacher/gradebook/categories/{$this->coursework->id}")->assertSessionHasErrors('term');
        $this->as()->post("/teacher/gradebook/assessments/{$archived->id}/restore")->assertSessionHasErrors('term');

        $this->assertNull($item->fresh()->archived_at);
        $this->assertNull($this->coursework->fresh()->archived_at);
        $this->assertNotNull($archived->fresh()->archived_at);
    }

    public function test_template_categories_cant_be_archived_but_their_items_can(): void
    {
        $nine = $this->levelSection('Grade 9-A', '9');
        TeachingAssignment::create(['section_id' => $nine->id, 'subject_id' => $this->math, 'teacher_id' => $this->teacher->id]);
        $pupil = $this->pupil('Nine Student', $nine);
        app(GradebookTemplateService::class)->adopt($nine, $this->math, $this->terms[0], $this->teacher->user);
        $quizzes = AssessmentCategory::where('section_id', $nine->id)->where('template_key', 'quizzes')->sole();
        $exams = AssessmentCategory::where('section_id', $nine->id)->where('template_key', 'examinations')->sole();
        foreach ([[$quizzes, 'Quiz 1', 10, 5], [$quizzes, 'Quiz 2', 10, 10], [$exams, 'Final', 100, 80]] as [$category, $name, $max, $score]) {
            $i = Assessment::create(['category_id' => $category->id, 'name' => $name, 'max_score' => $max]);
            Grade::create(['assessment_id' => $i->id, 'student_id' => $pupil->id, 'score' => $score, 'entered_by' => $this->teacher->id]);
        }
        $mark = fn () => app(GradeService::class)->sectionMarks($nine, $this->terms[0])[$pupil->id][$this->math];
        // Quizzes 15/20 = 75%: (75 x 10 + 80 x 60) / 70 = 79.29.
        $this->assertSame('79.29', $mark());

        $this->as()->delete("/teacher/gradebook/categories/{$quizzes->id}")->assertSessionHas('warning');
        $this->assertNull($quizzes->fresh()->archived_at);

        $this->as()->delete('/teacher/gradebook/assessments/'.Assessment::where('name', 'Quiz 1')->value('id'))->assertSessionHas('status');
        // Quizzes is now Quiz 2 alone, 10/10 = 100%: (100 x 10 + 80 x 60) / 70 = 82.86.
        $this->assertSame('82.86', $mark());
        $this->assertSame(3, Grade::count());
    }
}
