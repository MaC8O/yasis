<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 5: `gradebook:adopt-template` moves open-term KG1 to Grade 10 gradebooks onto the fixed
 * template without losing a grade, never touches a locked term, and is safe to run again.
 */
class AdoptTemplateTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $admin;

    private StaffProfile $teacher;

    private Section $section;

    private Student $student;

    private int $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $this->teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $this->section = $this->levelSection('Grade 5-A', '5');
        $this->math = Subject::where('code', 'MATH')->whereNull('department_id')->value('id');
        TeachingAssignment::create(['section_id' => $this->section->id, 'subject_id' => $this->math, 'teacher_id' => $this->teacher->id]);
        $this->student = $this->pupil('Grade Five', $this->section);
    }

    /** A teacher's own category with items, each graded. @param  array<string, array{0: string, 1: int}>  $items  name => [type, score] */
    private function category(string $name, int $weight, array $items, int $term = 0): AssessmentCategory
    {
        $category = AssessmentCategory::create(['section_id' => $this->section->id, 'subject_id' => $this->math,
            'term_id' => $this->terms[$term]->id, 'name' => $name, 'weight_pct' => $weight]);
        foreach ($items as $itemName => [$type, $score]) {
            $item = Assessment::create(['category_id' => $category->id, 'name' => $itemName, 'type' => $type, 'max_score' => 100]);
            Grade::create(['assessment_id' => $item->id, 'student_id' => $this->student->id, 'score' => $score, 'entered_by' => $this->teacher->id]);
        }

        return $category;
    }

    private function categoryOf(string $itemName): AssessmentCategory
    {
        return Assessment::where('name', $itemName)->sole()->category;
    }

    public function test_every_rule_moves_items_without_losing_a_grade(): void
    {
        $quiz = $this->category('Quiz', 20, ['Quiz 1' => ['quiz', 80], 'Quiz 2' => ['quiz', 70]]);
        $test = $this->category('Test', 20, ['Unit test' => ['test', 90]]);
        $homework = $this->category('Homework', 10, ['Worksheet' => ['assignment', 85]]);
        $exam = $this->category('Mid-term Exam', 30, ['Mid-term' => ['test', 75]]);
        $attendance = $this->category('Attendance marks', 10, ['Roll call' => ['assignment', 100]]);
        $misc = $this->category('Misc', 10, ['Final exam paper' => ['test', 65], 'Group poster' => ['group_work', 95]]);
        $grades = Grade::orderBy('id')->get(['id', 'assessment_id', 'score'])->toArray();

        $this->assertSame(0, Artisan::call('gradebook:adopt-template', ['--actor' => 'admin@test.local']));
        $this->assertStringContainsString('Moved 1 gradebook onto the template (3 items moved)', Artisan::output());

        // 1. A matching category becomes the template category, keeping its items.
        $this->assertSame(['quizzes', 'Class tests / quizzes', '10.00'], [$quiz->fresh()->template_key, $quiz->fresh()->name, (string) $quiz->fresh()->weight_pct]);
        $this->assertSame(['assignments', 'Assignments'], [$homework->fresh()->template_key, $homework->fresh()->name]);
        $this->assertSame(['examinations', '60.00'], [$exam->fresh()->template_key, (string) $exam->fresh()->weight_pct]);
        // 2. A second match is merged into the first and archived.
        $this->assertSame($quiz->id, $this->categoryOf('Unit test')->id);
        $this->assertNotNull($test->fresh()->archived_at);
        // 3. Items of a category that matches nothing move by name, then type.
        $this->assertSame('examinations', $this->categoryOf('Final exam paper')->template_key);
        $this->assertSame('assignments', $this->categoryOf('Group poster')->template_key);
        $this->assertNotNull($misc->fresh()->archived_at);
        // 4. Hand-entered attendance marks are archived with their items, not counted.
        $this->assertNotNull($attendance->fresh()->archived_at);
        $this->assertSame($attendance->id, $this->categoryOf('Roll call')->id);
        // 5. Missing categories are created: there are five current ones.
        $this->assertSame(['assignments', 'attendance', 'examinations', 'participation', 'quizzes'],
            AssessmentCategory::where('section_id', $this->section->id)->active()->orderBy('template_key')->pluck('template_key')->all());

        // No grade was deleted or changed.
        $this->assertSame($grades, Grade::orderBy('id')->get(['id', 'assessment_id', 'score'])->toArray());

        $log = AuditLog::where('action', 'Moved gradebook onto the template')->sole();
        $this->assertSame('admin', $log->role);
        $this->assertCount(3, $log->details['moved']);
        $this->assertContains('Test → Class tests / quizzes', $log->details['merged']);

        // The teacher is told once which items moved.
        $notice = $this->teacher->user->unreadNotifications()->sole();
        $this->assertContains('“Final exam paper” moved from Misc to Examinations', $notice->data['lines']);
        $this->assertContains('“Unit test” moved from Test to Class tests / quizzes', $notice->data['lines']);
    }

    public function test_it_is_safe_to_run_again_and_never_touches_a_locked_term(): void
    {
        $this->category('Quiz', 50, ['Quiz 1' => ['quiz', 80]]);
        $locked = $this->category('Homework', 100, ['Worksheet' => ['assignment', 85]], term: 1);
        $this->terms[1]->update(['is_locked' => true]);

        Artisan::call('gradebook:adopt-template', ['--actor' => 'admin@test.local']);
        Artisan::call('gradebook:adopt-template', ['--actor' => 'admin@test.local']);
        $this->assertStringContainsString('Nothing to change', Artisan::output());
        $this->assertSame(1, AuditLog::where('action', 'Moved gradebook onto the template')->count());

        $this->assertSame(['Homework', null, null, '100.00'], [$locked->fresh()->name, $locked->fresh()->template_key, $locked->fresh()->archived_at, (string) $locked->fresh()->weight_pct]);
        $this->assertSame(1, AssessmentCategory::where('term_id', $this->terms[1]->id)->count());
    }

    public function test_grade_11_and_12_gradebooks_are_left_alone(): void
    {
        $eleven = $this->levelSection('Grade 11-A', '11');
        $own = AssessmentCategory::create(['section_id' => $eleven->id, 'subject_id' => $this->math, 'term_id' => $this->terms[0]->id, 'name' => 'Coursework', 'weight_pct' => 40]);

        Artisan::call('gradebook:adopt-template', ['--actor' => 'admin@test.local']);

        $this->assertSame(['Coursework', null], [$own->fresh()->name, $own->fresh()->template_key]);
        $this->assertSame(1, AssessmentCategory::where('section_id', $eleven->id)->count());
    }

    public function test_the_command_needs_an_admin_or_principal(): void
    {
        $this->assertSame(1, Artisan::call('gradebook:adopt-template', ['--actor' => 'teacher@test.local']));
        $this->assertStringContainsString('Admin or Principal', Artisan::output());
    }
}
