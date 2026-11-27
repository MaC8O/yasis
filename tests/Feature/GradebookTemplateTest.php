<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\GradebookTemplateCategory;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Services\GradebookTemplateService;
use App\Services\GradeService;
use App\Services\ResultFreezeService;
use App\Services\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 5 (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4.1): KG1 to Grade 10 gradebooks use the
 * school's five fixed categories; a category scores marks earned over marks possible; Attendance
 * comes from the attendance records (Q8 defaults); the Admin edits the template, the Principal reads
 * it; Grades 11-12 keep free weighting; locked terms never change.
 */
class GradebookTemplateTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $teacher;

    private StaffProfile $admin;

    private Section $section;

    private Student $a;

    private Student $b;

    private int $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $this->teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $this->section = $this->levelSection('Grade 9-A', '9');
        $this->math = Subject::where('code', 'MATH')->whereNull('department_id')->value('id');
        TeachingAssignment::create(['section_id' => $this->section->id, 'subject_id' => $this->math, 'teacher_id' => $this->teacher->id]);
        $this->a = $this->pupil('Aye Aye', $this->section);
        $this->b = $this->pupil('Bo Bo', $this->section);
    }

    private function term(int $n = 1): Term
    {
        return $this->terms[$n - 1];
    }

    private function adopt(int $term = 1, ?Section $section = null, ?int $subject = null): array
    {
        return app(GradebookTemplateService::class)->adopt($section ?? $this->section, $subject ?? $this->math, $this->term($term), $this->admin->user);
    }

    private function cat(string $key, int $term = 1): AssessmentCategory
    {
        return AssessmentCategory::where('section_id', $this->section->id)->where('subject_id', $this->math)
            ->where('term_id', $this->term($term)->id)->where('template_key', $key)->active()->sole();
    }

    /** @param  array<int, int|float|null>  $scores  student id => score */
    private function item(string $key, string $name, int $max, array $scores, int $term = 1): Assessment
    {
        $item = Assessment::create(['category_id' => $this->cat($key, $term)->id, 'name' => $name, 'type' => 'quiz', 'max_score' => $max]);
        foreach ($scores as $studentId => $score) {
            Grade::create(['assessment_id' => $item->id, 'student_id' => $studentId, 'score' => $score, 'entered_by' => $this->teacher->id]);
        }

        return $item;
    }

    /** @param  list<string>  $statuses  one per school day, from the term's first day */
    private function attend(Student $student, array $statuses, int $term = 1): void
    {
        foreach ($statuses as $i => $status) {
            AttendanceRecord::create([
                'student_id' => $student->id, 'section_id' => $this->section->id, 'term_id' => $this->term($term)->id,
                'attendance_date' => $this->term($term)->start_date->copy()->addDays($i)->toDateString(),
                'status' => $status, 'recorded_by' => $this->teacher->id,
            ]);
        }
    }

    private function mark(Student $student, int $term = 1): ?string
    {
        return app(GradeService::class)->sectionMarks($this->section, $this->term($term))[$student->id][$this->math] ?? null;
    }

    private function gradebook()
    {
        return $this->actingAs($this->teacher->user)->get("/teacher/gradebook?section={$this->section->id}&subject={$this->math}&term={$this->term()->id}");
    }

    // --- The template in the gradebook ---------------------------------------------------------

    public function test_opening_a_kg_to_10_gradebook_puts_it_on_the_template(): void
    {
        $this->gradebook()->assertOk()
            ->assertSee("uses the school's fixed categories")
            ->assertDontSee('Add category');

        $categories = AssessmentCategory::where('section_id', $this->section->id)->where('term_id', $this->term()->id)->get();
        $this->assertSame(
            ['quizzes' => '10.00', 'attendance' => '10.00', 'assignments' => '10.00', 'participation' => '10.00', 'examinations' => '60.00'],
            $categories->sortBy('id')->mapWithKeys(fn ($c) => [$c->template_key => (string) $c->weight_pct])->all(),
        );
        $this->assertSame(1, AuditLog::where('action', 'Moved gradebook onto the template')->count());

        $this->gradebook()->assertOk();
        $this->assertSame(5, AssessmentCategory::where('section_id', $this->section->id)->where('term_id', $this->term()->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'Moved gradebook onto the template')->count());
    }

    public function test_a_category_scores_marks_earned_over_marks_possible(): void
    {
        $this->adopt();
        $this->item('quizzes', 'Quiz 1', 10, [$this->a->id => 8]);
        $this->item('quizzes', 'Quiz 2', 20, [$this->a->id => 15]);

        // 23 / 30 = 76.67%, not the mean of 80% and 75% (77.50%).
        $this->assertSame('76.67', $this->mark($this->a));
    }

    public function test_attendance_counts_present_and_tardy_and_leaves_excused_days_out(): void
    {
        $this->adopt();
        $this->item('quizzes', 'Quiz 1', 10, [$this->a->id => 10]);
        $this->attend($this->a, ['Present', 'Present', 'Tardy', 'Absent', 'Excused']);

        // Attendance 3 of 4 counted days = 75%; quizzes 100%: (100 x 10 + 75 x 10) / 20 = 87.50.
        $this->assertSame('87.50', $this->mark($this->a));
        $row = collect(app(GradeService::class)->categoryBreakdown($this->section, $this->term(), $this->a)[$this->math])->firstWhere('name', 'Attendance');
        $this->assertSame(['75', '3 of 4 days (1 excused not counted)'], [$row['avg'], $row['detail']]);
    }

    public function test_a_term_without_attendance_records_leaves_attendance_out(): void
    {
        $this->adopt();
        $this->item('quizzes', 'Quiz 1', 10, [$this->a->id => 9]);

        $this->assertSame('90.00', $this->mark($this->a));
        $row = collect(app(GradeService::class)->categoryBreakdown($this->section, $this->term(), $this->a)[$this->math])->firstWhere('name', 'Attendance');
        $this->assertSame([null, 'No attendance recorded'], [$row['avg'], $row['detail']]);
    }

    public function test_attendance_alone_does_not_make_a_mark(): void
    {
        $this->adopt();
        $this->attend($this->a, ['Present', 'Present']);

        $this->assertNull($this->mark($this->a));
    }

    public function test_only_a_students_own_records_count_and_missing_days_are_flagged(): void
    {
        $this->adopt();
        $this->item('quizzes', 'Quiz 1', 10, [$this->a->id => 10, $this->b->id => 10]);
        $this->attend($this->a, ['Present', 'Present', 'Absent', 'Present']); // 4 section school days
        $this->attend($this->b, ['Present', 'Absent']);                       // no record on days 3 and 4

        // Bo Bo: 1 of 2 own days = 50% (days 3 and 4 don't count against him): (100 + 50) / 2 = 75.
        $this->assertSame('75.00', $this->mark($this->b));
        $this->assertSame('87.50', $this->mark($this->a));

        $this->gradebook()->assertOk()->assertSee('Attendance records missing')->assertSee('Bo Bo: 2 days with no record')
            ->assertDontSee('Aye Aye: ');
        $this->actingAs($this->teacher->user)->get("/teacher/attendance?section={$this->section->id}&date=".$this->term()->start_date->toDateString())
            ->assertOk()->assertSee('Attendance records missing')->assertSee('Bo Bo: 2 days with no record');
    }

    public function test_a_teacher_cannot_add_remove_or_fill_the_fixed_categories(): void
    {
        $this->adopt();
        $as = $this->actingAs($this->teacher->user);

        $as->post('/teacher/gradebook/categories', ['section_id' => $this->section->id, 'subject_id' => $this->math, 'term_id' => $this->term()->id, 'name' => 'Homework', 'weight_pct' => 10])
            ->assertSessionHas('warning');
        $as->delete('/teacher/gradebook/categories/'.$this->cat('examinations')->id)->assertSessionHas('warning');
        $as->post('/teacher/gradebook/assessments', ['category_id' => $this->cat('attendance')->id, 'name' => 'Roll call', 'max_score' => 10])
            ->assertSessionHas('warning', 'Attendance has no items: its score comes from the attendance records.');

        $this->assertSame(5, AssessmentCategory::where('section_id', $this->section->id)->count());
        $this->assertSame(0, Assessment::count());
        $this->assertSame('60.00', (string) $this->cat('examinations')->weight_pct);
    }

    public function test_an_item_moves_to_another_category_with_its_grades(): void
    {
        $this->adopt();
        $item = $this->item('assignments', 'Unit test', 20, [$this->a->id => 18]);

        $this->actingAs($this->teacher->user)->patch("/teacher/gradebook/assessments/{$item->id}/move", ['category_id' => $this->cat('quizzes')->id])
            ->assertSessionHas('status');
        $this->assertSame($this->cat('quizzes')->id, $item->fresh()->category_id);
        $this->assertSame('18.00', (string) Grade::where('assessment_id', $item->id)->value('score'));
        $this->assertSame('teacher', AuditLog::where('action', 'Moved assessment to another category')->sole()->role);

        $this->actingAs($this->teacher->user)->patch("/teacher/gradebook/assessments/{$item->id}/move", ['category_id' => $this->cat('attendance')->id])
            ->assertSessionHas('warning');
        $this->assertSame($this->cat('quizzes')->id, $item->fresh()->category_id);
    }

    // --- The Admin's template --------------------------------------------------------------------

    private function templatePayload(array $overrides = []): array
    {
        $rows = GradebookTemplateCategory::ordered()->get()->mapWithKeys(fn ($c) => [$c->key => ['name' => $c->name, 'weight' => (string) $c->weight_pct]])->all();

        return ['categories' => array_replace_recursive($rows, $overrides)];
    }

    public function test_an_admin_template_change_is_audited_and_reaches_open_terms_only(): void
    {
        $this->adopt(1);
        $this->adopt(2);
        $principal = $this->makeStaff('principal', 'Principal');
        app(ResultFreezeService::class)->lock($this->term(2), $principal->user);

        $this->actingAs($this->admin->user)->put('/admin/gradebook-template', $this->templatePayload([
            'quizzes' => ['weight' => '20'], 'examinations' => ['name' => 'Exams', 'weight' => '50'],
        ]))->assertSessionHasNoErrors()->assertSessionHas('status');

        $log = AuditLog::where('action', 'Updated gradebook template')->sole();
        $this->assertSame('admin', $log->role);
        $this->assertSame(['from' => 'Examinations', 'to' => 'Exams'], $log->details['changes']['examinations']['name']);
        $this->assertSame(5, $log->details['open_term_categories_updated']); // Term 1's five; Term 2 is locked

        $this->assertSame(['Exams', '50.00'], [$this->cat('examinations', 1)->name, (string) $this->cat('examinations', 1)->weight_pct]);
        $this->assertSame('20.00', (string) $this->cat('quizzes', 1)->weight_pct);
        // The locked term keeps its categories.
        $this->assertSame(['Examinations', '60.00'], [$this->cat('examinations', 2)->name, (string) $this->cat('examinations', 2)->weight_pct]);

        // Weights must total 100.
        $this->actingAs($this->admin->user)->put('/admin/gradebook-template', $this->templatePayload(['quizzes' => ['weight' => '25']]))
            ->assertSessionHas('warning', 'The weights must total 100%. They total 105%.');
    }

    public function test_the_principal_sees_the_template_read_only_and_q8_is_flagged(): void
    {
        $principal = $this->makeStaff('principal', 'Principal');

        $this->actingAs($principal->user)->get('/principal/gradebook-template')->assertOk()
            ->assertSee('Participation / obedience / Code of Conduct')->assertSee('60%')
            ->assertSee('Q8 in the rules file')->assertSee('Needs confirmation from the school (Q9)')
            ->assertDontSee('Save template');
        $this->actingAs($principal->user)->put('/admin/gradebook-template', $this->templatePayload())->assertForbidden();

        $this->actingAs($this->admin->user)->put('/admin/gradebook-template', $this->templatePayload() + ['confirmed' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(GradebookTemplateCategory::where('key', 'attendance')->value('pending_question'));
    }

    // --- Grades 11-12 ------------------------------------------------------------------------------

    public function test_grade_11_keeps_free_weighting(): void
    {
        $eleven = $this->levelSection('Grade 11-A', '11');
        TeachingAssignment::create(['section_id' => $eleven->id, 'subject_id' => $this->math, 'teacher_id' => $this->teacher->id]);
        $student = $this->pupil('Eleven Student', $eleven);

        $this->actingAs($this->teacher->user)->post('/teacher/gradebook/categories', [
            'section_id' => $eleven->id, 'subject_id' => $this->math, 'term_id' => $this->term()->id, 'name' => 'Coursework', 'weight_pct' => 40,
        ])->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $coursework = AssessmentCategory::where('section_id', $eleven->id)->sole();
        $this->assertNull($coursework->template_key);
        foreach ([[10, 8], [20, 15]] as $i => [$max, $score]) {
            $item = Assessment::create(['category_id' => $coursework->id, 'name' => "Task {$i}", 'max_score' => $max]);
            Grade::create(['assessment_id' => $item->id, 'student_id' => $student->id, 'score' => $score, 'entered_by' => $this->teacher->id]);
        }

        // The mean of 80% and 75%, as before; no fixed categories were created.
        $this->assertSame('77.50', app(GradeService::class)->sectionMarks($eleven, $this->term())[$student->id][$this->math]);
        $this->assertSame([], app(GradebookTemplateService::class)->adopt($eleven, $this->math, $this->term(), $this->admin->user)['created']);
    }

    // --- Locking ---------------------------------------------------------------------------------

    public function test_a_locked_term_is_unchanged_by_template_and_attendance_changes(): void
    {
        $this->adopt();
        $this->item('quizzes', 'Quiz 1', 10, [$this->a->id => 8]);
        $this->item('examinations', 'Final', 100, [$this->a->id => 70]);
        $this->attend($this->a, ['Present', 'Absent']);
        $principal = $this->makeStaff('principal', 'Principal');
        app(ResultFreezeService::class)->lock($this->term(), $principal->user);

        // (80 x 10 + 70 x 60 + 50 x 10) / 80 = 68.75.
        $stored = fn () => app(ResultService::class)->sectionTerm($this->section, $this->term())->for($this->a)->subject('MATH')->mark;
        $this->assertSame('68.75', $stored());

        $this->actingAs($this->admin->user)->put('/admin/gradebook-template', $this->templatePayload([
            'quizzes' => ['weight' => '30'], 'examinations' => ['weight' => '40'],
        ]))->assertSessionHasNoErrors();
        $this->attend($this->a, [2 => 'Present', 3 => 'Present', 4 => 'Present']);

        $this->assertSame('68.75', $stored());
        $this->assertSame(['10.00', '60.00'], [(string) $this->cat('quizzes')->weight_pct, (string) $this->cat('examinations')->weight_pct]);
    }

    public function test_the_principal_is_warned_before_locking_with_empty_categories(): void
    {
        $this->adopt();
        $this->item('quizzes', 'Quiz 1', 10, [$this->a->id => 8, $this->b->id => 9]);
        $principal = $this->makeStaff('principal', 'Principal');
        $controls = ['promotion_window_open' => '1', 'transcript_issuance_enabled' => '0', 'principal_may_assist_registration' => '1', 'grade_lock' => '1'];

        $this->actingAs($principal->user)->get('/principal/governance')->assertOk()
            ->assertSee('gradebooks have an empty category')
            ->assertSee('Grade 9-A · MATHEMATICS: Assignments, Participation / obedience / Code of Conduct, Examinations')
            ->assertSee('Lock anyway');

        $this->actingAs($principal->user)->put('/principal/governance', $controls)->assertSessionHasErrors('acknowledge_empty_categories');
        $this->assertFalse($this->term()->fresh()->is_locked);

        $this->actingAs($principal->user)->put('/principal/governance', $controls + ['acknowledge_empty_categories' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($this->term()->fresh()->is_locked);

        // Mathematics had no Examinations items: no final mark, so the overall result is incomplete.
        $r = app(ResultService::class)->sectionTerm($this->section, $this->term())->for($this->a);
        $this->assertTrue($r->frozen);
        $this->assertNull($r->subject('MATH')->mark);
        $this->assertSame('incomplete', $r->status);
        $this->assertStringContainsString('No examination was set in MATHEMATICS this term', implode(' ', $r->reasons));
    }

    // --- The one-time notice ----------------------------------------------------------------------

    public function test_a_teacher_sees_the_moved_items_once(): void
    {
        $bonus = AssessmentCategory::create(['section_id' => $this->section->id, 'subject_id' => $this->math, 'term_id' => $this->term()->id, 'name' => 'Bonus round', 'weight_pct' => 100]);
        Assessment::create(['category_id' => $bonus->id, 'name' => 'Final exam paper', 'type' => 'test', 'max_score' => 50]);
        Assessment::create(['category_id' => $bonus->id, 'name' => 'Pop quiz', 'type' => 'quiz', 'max_score' => 10]);

        $this->gradebook()->assertOk()
            ->assertSee('your gradebook now uses the school\'s fixed categories')
            ->assertSee('“Final exam paper” moved from Bonus round to Examinations')
            ->assertSee('“Pop quiz” moved from Bonus round to Class tests / quizzes');

        $this->gradebook()->assertOk()->assertDontSee('“Pop quiz” moved from');
        $this->actingAs($this->teacher->user)->get('/notifications')->assertOk()->assertSee('“Pop quiz” moved from Bonus round');
    }
}
