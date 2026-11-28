<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentElective;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\TeachingAssignment;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 2 of the academic rules (sdp_doc/YASIS_Academic_and_Fee_Rules.md §3): the curriculum as
 * data, seeded with the school's subject lists and, since 9a, the school's answers to Q4-Q7 (one
 * Grade 10 default left, Computer, flagged FQ4), edited by the Admin, shown read-only to the
 * Principal, and driving the expected-subjects count.
 */
class CurriculumTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $admin;

    private Department $department;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->department = $this->seedDepartment();
        $this->term = $this->seedAcademicCalendar();
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
    }

    /** @return \Illuminate\Support\Collection<int, SubjectOffering> */
    private function rows(string $levelCode)
    {
        return SubjectOffering::with('subject')->whereHas('gradeLevel', fn ($q) => $q->where('code', $levelCode))->ordered()->get();
    }

    private function row(string $levelCode, string $subjectCode): SubjectOffering
    {
        return $this->rows($levelCode)->firstWhere('subject.code', $subjectCode);
    }

    private function asAdmin()
    {
        return $this->actingAs($this->admin->user);
    }

    private function rowPayload(SubjectOffering $row, array $overrides = []): array
    {
        return array_merge([
            'report_label' => $row->report_label,
            'is_compulsory' => $row->is_compulsory ? '1' : '0',
            'track' => $row->track,
            'credit_hours' => $row->credit_hours,
            'grading_mode' => $row->grading_mode,
            'counts_in_result' => $row->counts_in_result ? '1' : '0',
        ], $overrides);
    }

    // ------------------------------------------------------------------ the seeded curriculum

    public function test_the_school_subjects_are_school_wide(): void
    {
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE', 'CHAR', 'SOCST', 'COMP', 'PE', 'MUSIC', 'SUPW', 'BIO', 'CHEM', 'PHYS', 'WHIST', 'WGEO', 'ACCT'];

        $subjects = Subject::whereIn('code', $codes)->get();
        $this->assertCount(18, $subjects);
        $this->assertTrue($subjects->every(fn ($s) => $s->department_id === null));
        $this->assertSame(139, SubjectOffering::count());
    }

    public function test_kg1_to_grade_4_count_character_building_and_not_social_study(): void
    {
        foreach (['KG1', 'KG2', '1', '2', '3', '4'] as $level) {
            $rows = $this->rows($level);
            $this->assertSame(['MORAL/VALUES EDU', 'MYANMAR', 'ENGLISH', 'MATHEMATICS', 'SCIENCE', 'MAPE', 'CHARACTER BUILDING', 'SOCIAL STUDY'], $rows->pluck('report_label')->all(), $level);
            $this->assertSame(['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE', 'CHAR'], $rows->where('counts_in_result', true)->pluck('subject.code')->values()->all(), $level);
            $this->assertSame(0, $rows->whereNotNull('pending_question')->count(), $level); // Q4 answered
            $this->assertTrue($rows->every(fn ($r) => $r->is_compulsory && $r->grading_mode === 'score' && $r->credit_hours === null && $r->track === null));
        }
    }

    public function test_grades_5_to_9_count_nine_subjects_and_supw_has_a_word(): void
    {
        foreach (['5', '6', '7', '8', '9'] as $level) {
            $rows = $this->rows($level);
            $this->assertSame(['MORAL/VALUES EDU', 'MYANMAR', 'ENGLISH', 'MATHEMATICS', 'SCIENCE', 'SOCIAL STUDY', 'COMPUTER', 'PE', 'MUSIC', 'SUPW'], $rows->pluck('report_label')->all(), $level);
            $this->assertSame(9, $rows->where('counts_in_result', true)->count(), $level);
            $this->assertSame(['completion', false], [$this->row($level, 'SUPW')->grading_mode, $this->row($level, 'SUPW')->counts_in_result]);
            $this->assertSame(0, $rows->whereNotNull('pending_question')->count()); // Q5 answered
        }
    }

    public function test_grade_10_counts_the_compulsory_four_and_one_track_of_three(): void
    {
        $rows = $this->rows('10');

        $this->assertSame(['MORAL/VALUES EDU', 'MYANMAR', 'ENGLISH', 'MATHEMATICS', 'COMPUTER', 'BIOLOGY', 'CHEMISTRY', 'PHYSICS', 'WORLD HISTORY', 'WORLD GEOGRAPHY', 'MUSIC', 'SUPW', 'PE'], $rows->pluck('report_label')->all());
        $this->assertSame(['MORAL', 'MYAN', 'ENG', 'MATH'], $rows->where('is_compulsory', true)->where('counts_in_result', true)->pluck('subject.code')->values()->all());
        $tracks = $rows->whereNotNull('track')->groupBy('track')->map(fn ($g) => $g->pluck('subject.code')->all())->sortKeys()->all();
        $this->assertSame(['arts' => ['WHIST', 'WGEO', 'MUSIC'], 'science' => ['BIO', 'CHEM', 'PHYS']], $tracks);
        $this->assertTrue($rows->whereNotNull('track')->every(fn ($r) => $r->counts_in_result && ! $r->is_compulsory));
        // Extras: SUPW and PE. Computer isn't counted by default: follow-up question 4.
        $this->assertSame([false, 'completion'], [$this->row('10', 'SUPW')->counts_in_result, $this->row('10', 'SUPW')->grading_mode]);
        $this->assertFalse($this->row('10', 'PE')->counts_in_result);
        $this->assertSame([false, 'FQ4'], [$this->row('10', 'COMP')->counts_in_result, $this->row('10', 'COMP')->pending_question]);
        $this->assertSame(['COMP'], $rows->whereNotNull('pending_question')->pluck('subject.code')->values()->all()); // Q6 answered
    }

    public function test_grades_11_and_12_have_credit_hours_a_track_and_supw_with_a_word(): void
    {
        foreach (['11', '12'] as $level) {
            $rows = $this->rows($level);
            $compulsory = $rows->where('is_compulsory', true)->where('counts_in_result', true);
            $this->assertSame(['MORAL' => '3.0', 'MYAN' => '3.0', 'ENG' => '3.0', 'MATH' => '3.0', 'COMP' => '2.0', 'MUSIC' => '2.0', 'PE' => '1.0'],
                $compulsory->mapWithKeys(fn ($r) => [$r->subject->code => $r->credit_hours])->all());
            $this->assertSame('17.0', number_format($compulsory->sum(fn ($r) => (float) $r->credit_hours), 1));

            $tracks = $rows->whereNotNull('track')->groupBy('track')->map(fn ($g) => $g->pluck('subject.code')->all())->sortKeys()->all();
            $this->assertSame(['arts' => ['WHIST', 'WGEO', 'ACCT'], 'science' => ['BIO', 'CHEM', 'PHYS']], $tracks);
            $optional = $rows->whereNotNull('track');
            $this->assertTrue($optional->every(fn ($r) => ! $r->is_compulsory && $r->credit_hours === '2.0' && $r->counts_in_result && $r->pending_question === null && $r->elective_slot === null));

            $supw = $this->row($level, 'SUPW');
            $this->assertSame(['completion', false, null, null], [$supw->grading_mode, $supw->counts_in_result, $supw->credit_hours, $supw->pending_question]);
        }

        $this->asAdmin()->get('/admin/curriculum?level=11')->assertOk()
            ->assertSee('one track per student: arts or science')
            ->assertSee('a student takes 23 credit hours (17 compulsory + 6 in their track)');
    }

    public function test_rolling_back_the_seed_is_refused_once_a_curriculum_subject_is_in_use(): void
    {
        $migration = require database_path('migrations/2026_11_26_090300_seed_school_curriculum.php');
        $section = Section::create(['academic_year_id' => $this->term->academic_year_id, 'department_id' => $this->department->id,
            'grade_level_id' => GradeLevel::where('code', '9')->value('id'), 'name' => 'Grade 9-A', 'capacity' => 30]);
        TeachingAssignment::create(['section_id' => $section->id, 'subject_id' => Subject::where('code', 'MATH')->value('id'),
            'teacher_id' => $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->id]);

        try {
            $migration->down();
            $this->fail('The rollback should have been refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not rolling back', $e->getMessage());
        }
        $this->assertSame(139, SubjectOffering::count());
        $this->assertSame(18, Subject::whereNull('department_id')->count());
    }

    // ------------------------------------------------------------------ Admin page

    public function test_the_admin_page_shows_a_levels_rows_in_order_with_their_flags(): void
    {
        $this->asAdmin()->get('/admin/curriculum')->assertOk()
            ->assertSee('Kindergarten 1')
            ->assertSeeInOrder(['MORAL/VALUES EDU', 'MYANMAR', 'ENGLISH', 'MATHEMATICS', 'SCIENCE', 'MAPE', 'CHARACTER BUILDING', 'SOCIAL STUDY'])
            ->assertSee('7 counted in the result')
            ->assertDontSee('Needs confirmation from the school')
            ->assertSee('Curriculum'); // navigation

        $this->asAdmin()->get('/admin/curriculum?level=10')->assertOk()
            ->assertSee('7 counted per student (4 compulsory + 3 from their track)')
            ->assertSee('Track · science')
            ->assertSee('Needs confirmation from the school (FQ4)')
            ->assertSee('1 row uses a default that', false);
    }

    public function test_the_admin_edits_a_row_and_confirms_it(): void
    {
        $row = $this->row('10', 'COMP');

        $this->asAdmin()->put("/admin/curriculum/rows/{$row->id}", $this->rowPayload($row, ['counts_in_result' => '1', 'confirmed' => '1']))
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $row->refresh();
        $this->assertTrue($row->counts_in_result);
        $this->assertNull($row->pending_question);
        $log = AuditLog::where('action', 'Updated curriculum row')->sole();
        $this->assertSame('admin', $log->role);
        $this->assertSame('10', $log->details['grade_level']);
        $this->assertSame('COMP', $log->details['subject']);
        $this->assertSame(['from' => false, 'to' => true], $log->details['changes']['counts_in_result']);
        $this->assertSame(['from' => 'FQ4', 'to' => null], $log->details['changes']['pending_question']);

        // Saving the same values again changes nothing.
        $this->asAdmin()->put("/admin/curriculum/rows/{$row->id}", $this->rowPayload($row))->assertSessionHas('warning', 'Nothing was changed.');
        $this->assertSame(1, AuditLog::where('action', 'Updated curriculum row')->count());
    }

    public function test_editing_without_the_confirmed_tick_keeps_the_flag(): void
    {
        $row = $this->row('10', 'COMP');

        $this->asAdmin()->put("/admin/curriculum/rows/{$row->id}", $this->rowPayload($row, ['report_label' => 'COMPUTER STUDIES']))->assertSessionHasNoErrors();

        $this->assertSame('COMPUTER STUDIES', $row->fresh()->report_label);
        $this->assertSame('FQ4', $row->fresh()->pending_question);
    }

    public function test_a_row_must_be_consistent(): void
    {
        $compulsory = $this->row('11', 'MATH');
        $optional = $this->row('11', 'BIO');

        $this->asAdmin()->put("/admin/curriculum/rows/{$compulsory->id}", $this->rowPayload($compulsory, ['track' => 'science']))
            ->assertSessionHasErrors(['track' => 'A compulsory subject belongs to no track. Clear the track, or make the subject optional.']);
        $this->asAdmin()->put("/admin/curriculum/rows/{$optional->id}", $this->rowPayload($optional, ['track' => '']))
            ->assertSessionHasErrors(['track' => 'An optional subject needs the track it belongs to (for example science or arts).']);
        $this->asAdmin()->put("/admin/curriculum/rows/{$optional->id}", $this->rowPayload($optional, ['track' => 'Science 2']))->assertSessionHasErrors('track');
        $this->asAdmin()->put("/admin/curriculum/rows/{$compulsory->id}", $this->rowPayload($compulsory, ['credit_hours' => '2.25']))->assertSessionHasErrors('credit_hours');
        $this->asAdmin()->put("/admin/curriculum/rows/{$compulsory->id}", $this->rowPayload($compulsory, ['credit_hours' => '-1']))->assertSessionHasErrors('credit_hours');
        $this->asAdmin()->put("/admin/curriculum/rows/{$compulsory->id}", $this->rowPayload($compulsory, ['grading_mode' => 'letters']))->assertSessionHasErrors('grading_mode');
        $this->asAdmin()->put("/admin/curriculum/rows/{$compulsory->id}", $this->rowPayload($compulsory, ['report_label' => '']))->assertSessionHasErrors('report_label');

        $this->assertSame('3.0', $compulsory->fresh()->credit_hours);
        $this->assertNull($compulsory->fresh()->track);
        $this->assertSame(0, AuditLog::where('action', 'Updated curriculum row')->count());
    }

    public function test_credit_hours_are_stored_as_decimals(): void
    {
        $row = $this->row('11', 'PE');

        $this->asAdmin()->put("/admin/curriculum/rows/{$row->id}", $this->rowPayload($row, ['credit_hours' => '1.5']))->assertSessionHasNoErrors();
        $this->assertSame('1.5', $row->fresh()->credit_hours);

        $this->asAdmin()->put("/admin/curriculum/rows/{$row->id}", $this->rowPayload($row->fresh(), ['credit_hours' => '']))->assertSessionHasNoErrors();
        $this->assertNull($row->fresh()->credit_hours);
    }

    public function test_the_admin_adds_a_catalogue_subject_to_a_level(): void
    {
        $bible = Subject::create(['code' => 'HS-BIB', 'name' => 'Bible', 'department_id' => $this->department->id]);
        $level = GradeLevel::where('code', '9')->first();

        $this->asAdmin()->post("/admin/curriculum/levels/{$level->id}", [
            'subject_id' => $bible->id, 'report_label' => 'BIBLE', 'is_compulsory' => '1', 'grading_mode' => 'score', 'counts_in_result' => '0',
        ])->assertSessionHasNoErrors();

        $row = $this->rows('9')->last();
        $this->assertSame(['HS-BIB', 'BIBLE', 11, false], [$row->subject->code, $row->report_label, $row->sort_order, $row->counts_in_result]);
        $log = AuditLog::where('action', 'Added subject to curriculum')->sole();
        $this->assertSame(['admin', '9', 'HS-BIB'], [$log->role, $log->details['grade_level'], $log->details['subject']]);

        // The same subject twice is refused.
        $this->asAdmin()->post("/admin/curriculum/levels/{$level->id}", [
            'subject_id' => $bible->id, 'report_label' => 'BIBLE 2', 'is_compulsory' => '1', 'grading_mode' => 'score',
        ])->assertSessionHasErrors('subject_id');
        $this->assertSame(11, $this->rows('9')->count());
    }

    public function test_the_admin_reorders_rows_within_one_level_only(): void
    {
        $science = $this->row('9', 'SCI');

        $this->asAdmin()->post("/admin/curriculum/rows/{$science->id}/move/up")->assertSessionHasNoErrors();
        $this->assertSame(['MORAL', 'MYAN', 'ENG', 'SCI', 'MATH'], $this->rows('9')->take(5)->pluck('subject.code')->all());
        $this->assertSame(range(1, 10), $this->rows('9')->pluck('sort_order')->all());
        $this->assertSame(['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI'], $this->rows('8')->take(5)->pluck('subject.code')->all());

        $this->asAdmin()->post("/admin/curriculum/rows/{$this->row('9', 'MORAL')->id}/move/up")->assertSessionHas('warning');
        $this->asAdmin()->post("/admin/curriculum/rows/{$this->row('9', 'SUPW')->id}/move/down")->assertSessionHas('warning');
        $log = AuditLog::where('action', 'Moved curriculum row')->sole();
        $this->assertSame(['admin', 'SCI', 'up'], [$log->role, $log->details['subject'], $log->details['direction']]);
    }

    public function test_retiring_a_row_removes_it_from_the_expected_subjects_and_restoring_brings_it_back(): void
    {
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        Section::create(['academic_year_id' => $this->term->academic_year_id, 'department_id' => $this->department->id,
            'grade_level_id' => GradeLevel::where('code', '9')->value('id'), 'name' => 'Grade 9-A', 'capacity' => 30]);
        $row = $this->row('9', 'SOCST');

        $this->asAdmin()->post("/admin/curriculum/rows/{$row->id}/retire")->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertNotNull($row->fresh()->archived_at);
        $this->assertDatabaseHas('subject_offerings', ['id' => $row->id]); // kept, not deleted
        $this->actingAs($registrar->user)->get('/registrar/sections')->assertSee('0 / 9');

        $this->asAdmin()->post("/admin/curriculum/rows/{$row->id}/restore")->assertSessionHas('status');
        $this->assertNull($row->fresh()->archived_at);
        $this->actingAs($registrar->user)->get('/registrar/sections')->assertSee('0 / 10');

        $this->assertSame(['Retired curriculum row', 'Restored curriculum row'],
            AuditLog::where('entity_type', 'SubjectOffering')->orderBy('id')->pluck('action')->all());
    }

    public function test_a_row_chosen_as_an_elective_this_year_cannot_be_retired(): void
    {
        $biology = $this->row('11', 'BIO');
        $student = Student::create(['student_id_number' => 'S-1', 'name' => 'Test Student', 'admission_date' => now(), 'department_id' => $this->department->id, 'enrollment_status' => 'Enrolled']);
        StudentElective::create(['student_id' => $student->id, 'academic_year_id' => $this->term->academic_year_id, 'elective_slot' => 'A', 'subject_offering_id' => $biology->id]);

        $this->asAdmin()->post("/admin/curriculum/rows/{$biology->id}/retire")
            ->assertSessionHas('warning', 'BIOLOGY can\'t be retired: 1 student has chosen it this year. Change their elective choice first.');
        $this->assertNull($biology->fresh()->archived_at);
    }

    // ------------------------------------------------------------------ Principal, VP and access

    public function test_the_principal_sees_the_curriculum_read_only(): void
    {
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');

        $this->actingAs($principal->user)->get('/principal/curriculum?level=10')->assertOk()
            ->assertSeeInOrder(['MORAL/VALUES EDU', 'MYANMAR', 'ENGLISH'])
            ->assertSee('Needs confirmation from the school (FQ4)')
            ->assertSee('Curriculum') // navigation
            ->assertDontSee('/admin/curriculum/rows', false)
            ->assertDontSee('Add a subject to')
            ->assertDontSee('Save row');
    }

    public function test_only_the_admin_changes_the_curriculum(): void
    {
        $row = $this->row('9', 'MATH');
        $level = GradeLevel::where('code', '9')->first();
        $others = [
            $this->makeStaff('principal', 'Principal', 'principal@test.local')->user,
            $this->makeStaff('registrar', 'Registrar', 'registrar@test.local')->user,
            $this->makeStaff('teacher', 'Teacher', 'teacher@test.local')->user,
            $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local')->user,
        ];

        foreach ($others as $user) {
            $this->actingAs($user)->get('/admin/curriculum')->assertForbidden();
            $this->actingAs($user)->put("/admin/curriculum/rows/{$row->id}", $this->rowPayload($row, ['report_label' => 'X']))->assertForbidden();
            $this->actingAs($user)->post("/admin/curriculum/levels/{$level->id}", ['subject_id' => $row->subject_id])->assertForbidden();
            $this->actingAs($user)->post("/admin/curriculum/rows/{$row->id}/move/down")->assertForbidden();
            $this->actingAs($user)->post("/admin/curriculum/rows/{$row->id}/retire")->assertForbidden();
        }
        foreach (array_slice($others, 1) as $user) {
            $this->actingAs($user)->get('/principal/curriculum')->assertForbidden();
        }

        $this->assertSame('MATHEMATICS', $row->fresh()->report_label);
        $this->assertSame(0, AuditLog::where('entity_type', 'SubjectOffering')->count());
    }

    public function test_the_vp_catalogue_shows_school_wide_subjects_and_keeps_curriculum_subjects(): void
    {
        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        $math = Subject::where('code', 'MATH')->first();

        $this->actingAs($vp->user)->get('/vp_academic/subjects')->assertOk()->assertSee('School-wide');
        $this->actingAs($vp->user)->delete("/vp_academic/subjects/{$math->id}")
            ->assertSessionHasErrors(['subject' => 'MATH is in a grade level\'s curriculum and cannot be removed. The Admin can retire it from the curriculum instead.']);
        $this->assertDatabaseHas('subjects', ['id' => $math->id]);
    }
}
