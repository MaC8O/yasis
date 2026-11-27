<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\PersonalityRating;
use App\Models\PromotionBatch;
use App\Models\ReportAcknowledgement;
use App\Models\ReportRule;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Services\ReportCardService;
use App\Services\ResultFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 6 (sdp_doc/YASIS_Academic_and_Fee_Rules.md §7.1, §8): the four report cards, personality
 * ratings, acknowledgements, class placement and the attendance eligibility flag.
 */
class ReportCardTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $homeroom;

    private StaffProfile $principal;

    private Section $section;

    private Student $student;

    private const FOOTNOTE = '* Any student who has more than ten per cent absence in a subject in a term may be denied permission to write the final examination. (Or) A minimum of 80% attendance is required to sit for any examination.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->homeroom = $this->makeStaff('teacher', 'Teacher', 'homeroom@test.local');
        $this->principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        $this->section = $this->levelSection('Grade 2-A', '2');
        $this->section->update(['homeroom_teacher_id' => $this->homeroom->id]);
        $this->student = $this->pupil('Card Student', $this->section);
        $this->setMarks($this->student, $this->section, $this->terms[0], self::KG_EXAMPLE);
    }

    private function card(?Student $student = null, int $term = 1): array
    {
        return app(ReportCardService::class)->build($student ?? $this->student, $this->terms[$term - 1]);
    }

    private function attend(Student $student, array $statuses, int $term = 1, ?Section $section = null): void
    {
        foreach ($statuses as $i => $status) {
            AttendanceRecord::create([
                'student_id' => $student->id, 'section_id' => ($section ?? $this->section)->id, 'term_id' => $this->terms[$term - 1]->id,
                'attendance_date' => $this->terms[$term - 1]->start_date->copy()->addDays($i)->toDateString(),
                'status' => $status, 'recorded_by' => $this->homeroom->id,
            ]);
        }
    }

    private function lock(int $term = 1): void
    {
        app(ResultFreezeService::class)->lock($this->terms[$term - 1], $this->principal->user);
    }

    private function guardianFor(Student $student): User
    {
        $user = User::create(['name' => 'Parent of '.$student->name, 'email' => "parent{$student->id}@test.local", 'password' => Hash::make('password'), 'status' => 'Active']);
        $user->assignRole('guardian');
        Guardian::create(['user_id' => $user->id, 'relationship' => 'Mother', 'phone' => '09'])->students()->attach($student->id, ['is_primary' => true]);

        return $user;
    }

    // --- The four layouts ---------------------------------------------------------------------------

    public function test_each_grade_level_gets_its_card_with_the_schools_subjects_in_order(): void
    {
        $kg = $this->card();
        $this->assertSame(['kg4', 'blue', false, 'G.P.A (AVERAGE %)'], [$kg['layout'], $kg['accent'], $kg['hasHours'], $kg['gpaLabel']]);
        $this->assertSame(['MORAL/VALUES EDU', 'MYANMAR', 'ENGLISH', 'MATHEMATICS', 'SCIENCE', 'MAPE', 'CHARACTER BUILDING', 'SOCIAL STUDY'], array_column($kg['subjects'], 'label'));
        $this->assertSame(['89', '90'], [$kg['subjects'][0]['cells'][1], $kg['subjects'][1]['cells'][1]]);
        $this->assertSame(['90', '1', '1'], [$kg['gpaRow'][1], $kg['positionRow'][1], $kg['totalRow'][1]]);

        $seven = $this->levelSection('Grade 7-A', '7');
        $c7 = $this->card($this->pupil('Seven', $seven));
        $this->assertSame(['g5_9', 'green'], [$c7['layout'], $c7['accent']]);
        $this->assertSame('SUPW', last(array_column($c7['subjects'], 'label')));
        $this->assertCount(10, $c7['subjects']);

        $ten = $this->levelSection('Grade 10-A', '10');
        $c10 = $this->card($this->pupil('Ten', $ten));
        $this->assertSame(['g10', 'yellow', 'G.P.A (POINT TOTAL)'], [$c10['layout'], $c10['accent'], $c10['gpaLabel']]);
        $this->assertCount(13, $c10['subjects']);

        $twelve = $this->levelSection('Grade 12-A', '12');
        $c12 = $this->card($this->grade12Example($twelve, $this->terms[0]));
        $this->assertSame(['g11_12', 'yellow', true, 'G.P.A'], [$c12['layout'], $c12['accent'], $c12['hasHours'], $c12['gpaLabel']]);
        $rows = collect($c12['subjects'])->keyBy('label');
        $this->assertSame(['3', '66'], [$rows['MYANMAR']['hours'], $rows['MYANMAR']['cells'][1]]);
        $this->assertSame('-', $rows['WORLD HISTORY']['cells'][1]); // an option not taken
        $this->assertSame('PASS', $rows['SUPW']['cells'][1]);
        $this->assertSame('3.04', $c12['gpaRow'][1]);
    }

    public function test_the_card_prints_the_footnote_word_for_word_and_marks_open_terms_provisional(): void
    {
        $user = User::create(['name' => 'Card Student', 'email' => 'student@test.local', 'password' => Hash::make('password'), 'status' => 'Active']);
        $user->assignRole('student');
        $this->student->update(['user_id' => $user->id]);
        $this->terms[0]->update(['results_released' => true]);

        $this->actingAs($user)->get("/student/report-cards/{$this->terms[0]->id}")->assertOk()
            ->assertSee(self::FOOTNOTE)->assertSee('Provisional.')
            ->assertSee('This progress report is sent to you once in every nine week of the school year.');
        $html = view('report-cards.pdf', ['card' => $this->card()])->render();
        $this->assertStringContainsString(e(self::FOOTNOTE), $html);
        $this->assertStringContainsString('PROVISIONAL', $html);

        $this->lock();
        $this->assertStringNotContainsString('PROVISIONAL', view('report-cards.pdf', ['card' => $this->card()])->render());
    }

    public function test_the_final_column_is_the_fourth_terms_own_result(): void
    {
        foreach ([2 => 70, 3 => 80, 4 => 60] as $term => $math) {
            $this->setMarks($this->student, $this->section, $this->terms[$term - 1], array_merge(self::KG_EXAMPLE, ['MATH' => $math]));
        }

        $second = $this->card(term: 2);
        $math = collect($second['subjects'])->firstWhere('label', 'MATHEMATICS');
        $this->assertSame([1 => '82', 2 => '70', 3 => '', 4 => ''], $math['cells']); // later terms blank

        $final = collect($this->card(term: 4)['subjects'])->firstWhere('label', 'MATHEMATICS');
        // Term 4's own mark (60), not the year average (73).
        $this->assertSame([1 => '82', 2 => '70', 3 => '80', 4 => '60'], $final['cells']);
    }

    // --- Personality -------------------------------------------------------------------------------

    public function test_the_homeroom_teacher_rates_personality_until_the_term_is_locked(): void
    {
        $url = "/report-cards/{$this->section->id}/{$this->terms[0]->id}/personality";
        $this->actingAs($this->homeroom->user)->put($url, ['ratings' => [$this->student->id => ['creative_thinking' => 'A', 'discipline' => 'B']]])
            ->assertSessionHas('status', 'Saved 2 ratings.');
        $this->assertSame(['creative_thinking' => 'A', 'discipline' => 'B'], $this->card()['personality']['ratings']);
        $this->assertSame('teacher', AuditLog::where('action', 'Rated personality traits')->sole()->role);

        $this->actingAs($this->homeroom->user)->put($url, ['ratings' => [$this->student->id => ['creative_thinking' => 'D']]])->assertSessionHasErrors();
        $other = $this->makeStaff('teacher', 'Teacher', 'other@test.local');
        $this->actingAs($other->user)->put($url, ['ratings' => [$this->student->id => ['social' => 'C']]])->assertForbidden();

        $this->lock();
        $this->actingAs($this->homeroom->user)->put($url, ['ratings' => [$this->student->id => ['social' => 'C']]])->assertSessionHas('warning');
        $this->assertSame(2, PersonalityRating::count());
    }

    public function test_the_card_prints_the_latest_rated_term_or_the_reports_own_term(): void
    {
        PersonalityRating::create(['student_id' => $this->student->id, 'term_id' => $this->terms[0]->id, 'trait' => 'social', 'rating' => 'A', 'rated_by' => $this->homeroom->id]);

        $card = $this->card(term: 2);
        $this->assertSame([$this->terms[0]->id, ['social' => 'A']], [$card['personality']['term']->id, $card['personality']['ratings']]);

        ReportRule::where('key', 'personality_shown')->update(['value' => 'report_term']);
        $this->assertNull($this->card(term: 2)['personality']['term']);
    }

    // --- Acknowledgements ----------------------------------------------------------------------------

    public function test_the_class_teacher_confirms_only_once_the_term_is_locked(): void
    {
        $confirm = "/report-cards/{$this->section->id}/{$this->terms[0]->id}/confirm";
        $this->actingAs($this->homeroom->user)->get("/report-cards?section={$this->section->id}&term={$this->terms[0]->id}")->assertOk()
            ->assertSee('You can confirm once the Principal has locked First');
        $this->actingAs($this->homeroom->user)->post($confirm)->assertSessionHas('warning');
        $this->assertSame(0, ReportAcknowledgement::count());

        $this->lock();
        $this->actingAs($this->homeroom->user)->post($confirm)->assertSessionHas('status', 'Confirmed 1 report for First.');
        $sig = $this->card()['acknowledgements'][1]['class_teacher'];
        $this->assertSame(['Teacher Test User', now()->format('j M Y')], [$sig['name'], $sig['date']]);
        $this->assertSame(1, AuditLog::where('action', 'Confirmed term reports')->count());
    }

    public function test_releasing_results_is_the_principals_sign_off_and_warns_about_unconfirmed_cards(): void
    {
        $controls = ['promotion_window_open' => '1', 'transcript_issuance_enabled' => '0', 'principal_may_assist_registration' => '1', 'acknowledge_empty_categories' => '1'];
        $this->actingAs($this->principal->user)->get('/principal/governance')->assertOk()
            ->assertSee("report cards the class teacher hasn't confirmed", false)->assertSee('Grade 2-A: 1 of 1 unconfirmed');

        // Released anyway, with a warning naming the class.
        $this->actingAs($this->principal->user)->put('/principal/governance', $controls + ['results_released' => '1'])
            ->assertSessionHas('status')->assertSessionHas('warning');
        $this->assertStringContainsString('Grade 2-A (1 of 1)', session('warning'));
        $this->assertTrue($this->terms[0]->fresh()->results_released);
        $this->assertSame('Principal Test User', $this->card()['acknowledgements'][1]['principal']['name']);

        // Turning the release off withdraws the sign-off (kept).
        $this->actingAs($this->principal->user)->put('/principal/governance', $controls);
        $this->assertNull($this->card()['acknowledgements'][1]['principal']);
        $this->assertSame(1, ReportAcknowledgement::whereNotNull('withdrawn_at')->count());
    }

    public function test_a_guardian_acknowledges_a_released_report_once(): void
    {
        $guardian = $this->guardianFor($this->student);
        $url = "/guardian/report-cards/{$this->terms[0]->id}/acknowledge?child={$this->student->id}";

        $this->actingAs($guardian)->post($url)->assertSessionHas('warning'); // not released
        $this->terms[0]->update(['results_released' => true]);
        $this->actingAs($guardian)->get("/guardian/report-cards/{$this->terms[0]->id}?child={$this->student->id}")->assertOk()
            ->assertSee('I acknowledge this report');
        $this->actingAs($guardian)->post($url)->assertSessionHas('status');
        $this->actingAs($guardian)->post($url)->assertSessionHas('warning'); // only once

        $this->assertSame(1, ReportAcknowledgement::where('role', 'guardian')->count());
        $this->assertSame('Parent of Card Student', $this->card()['acknowledgements'][1]['guardian']['name']);
        $this->actingAs($guardian)->get("/guardian/report-cards/{$this->terms[0]->id}?child={$this->student->id}")->assertOk()
            ->assertSee('Acknowledged by Parent of Card Student')->assertDontSee('I acknowledge this report');
    }

    // --- Placement -----------------------------------------------------------------------------------

    public function test_the_final_report_prints_the_class_placement_from_the_applied_promotion(): void
    {
        $nextYear = \App\Models\AcademicYear::create(['year_label' => '2027-2028', 'is_active' => false]);
        \App\Models\Term::create(['academic_year_id' => $nextYear->id, 'name' => 'First', 'sequence' => 1, 'start_date' => '2027-06-01', 'end_date' => '2027-08-01']);
        $three = Section::create(['academic_year_id' => $nextYear->id, 'department_id' => $this->resultsDepartment->id,
            'grade_level_id' => \App\Models\GradeLevel::where('code', '3')->value('id'), 'name' => 'Grade 3-A', 'capacity' => 30]);
        $batch = PromotionBatch::create(['from_section_id' => $this->section->id, 'prepared_by' => $this->homeroom->id, 'status' => 'Applied']);
        $batch->items()->create(['student_id' => $this->student->id, 'action' => 'Promote', 'to_section_id' => $three->id]);

        $this->assertNull($this->card(term: 3)['placement']);
        $this->assertSame(['grade' => 'Grade 3', 'year' => '2027-2028', 'beginning' => '1 Jun 2027'], $this->card(term: 4)['placement']);
    }

    // --- Attendance (days absent, eligibility) ----------------------------------------------------------

    public function test_days_absent_counts_absent_and_excused_and_is_frozen_with_the_term(): void
    {
        $this->attend($this->student, ['Present', 'Absent', 'Excused', 'Tardy', 'Absent']);
        $this->assertSame('3', $this->card()['daysAbsentRow'][1]);

        ReportRule::where('key', 'days_absent_counts')->update(['value' => 'absent_only']);
        $this->assertSame('2', $this->card()['daysAbsentRow'][1]);
        ReportRule::where('key', 'days_absent_counts')->update(['value' => 'absent_and_excused']);

        $this->lock();
        AttendanceRecord::where('student_id', $this->student->id)->where('status', 'Absent')->update(['status' => 'Present']);
        $this->assertSame('3', $this->card()['daysAbsentRow'][1]); // frozen at lock
    }

    public function test_students_below_the_minimum_attendance_are_flagged_never_blocked(): void
    {
        $exactly = $this->pupil('Exactly Eighty', $this->section);
        $below = $this->pupil('Below Eighty', $this->section);
        // 8 of 10 counted (Tardy attends, Excused left out) = 80%: not below.
        $this->attend($exactly, ['Present', 'Present', 'Tardy', 'Present', 'Present', 'Present', 'Present', 'Present', 'Absent', 'Absent', 'Excused']);
        // 7 of 9 = 77.8%: below.
        $this->attend($below, ['Present', 'Present', 'Present', 'Present', 'Present', 'Present', 'Tardy', 'Absent', 'Absent']);

        $vp = $this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local');
        $this->actingAs($vp->user)->get('/report-cards/attendance')->assertOk()
            ->assertSee('Below Eighty')->assertSee('77.8%')->assertDontSee('Exactly Eighty');
        $this->actingAs($vp->user)->get('/vp_academic/dashboard')->assertOk()->assertSee('1 student below 80% attendance');
        $this->actingAs($this->principal->user)->get('/principal/dashboard')->assertOk()->assertSee('1 student below 80% attendance');
        $this->actingAs($this->homeroom->user)->get("/report-cards?section={$this->section->id}&term={$this->terms[0]->id}")->assertOk()
            ->assertSee('Below the 80% attendance minimum')->assertSee('Below Eighty: 77.8%');

        ReportRule::where('key', 'min_attendance_pct')->update(['value' => '75']);
        $this->actingAs($vp->user)->get('/report-cards/attendance')->assertOk()->assertDontSee('Below Eighty');

        // Nothing blocks the student: the card and the result are still there.
        $this->assertSame('2', $this->card($below)['daysAbsentRow'][1]);
        $this->actingAs($vp->user)->get("/report-cards/{$this->section->id}/{$this->terms[0]->id}/{$below->id}")->assertOk()->assertSee('Below Eighty');
        $this->actingAs($this->homeroom->user)->put("/report-cards/{$this->section->id}/{$this->terms[0]->id}/personality", ['ratings' => [$below->id => ['social' => 'B']]])
            ->assertSessionHas('status');
    }

    // --- Access and rules ---------------------------------------------------------------------------

    public function test_who_sees_which_cards_and_the_same_figures_for_staff_student_and_guardian(): void
    {
        $other = $this->makeStaff('teacher', 'Teacher', 'other@test.local');
        $show = "/report-cards/{$this->section->id}/{$this->terms[0]->id}/{$this->student->id}";
        $this->actingAs($this->homeroom->user)->get($show)->assertOk()->assertSee('Scholarship record');
        $this->actingAs($other->user)->get($show)->assertForbidden();
        $registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        $staffHtml = $this->actingAs($registrar->user)->get($show)->assertOk()->getContent();

        $this->terms[0]->update(['results_released' => true]);
        $guardian = $this->guardianFor($this->student);
        $guardianHtml = $this->actingAs($guardian)->get("/guardian/report-cards/{$this->terms[0]->id}?child={$this->student->id}")->assertOk()->getContent();

        $rows = fn ($html) => preg_match('/data-scholarship>(.*?)<\/table>/s', $html, $m) ? preg_replace('/\s+/', ' ', strip_tags($m[1])) : null;
        $this->assertNotNull($rows($staffHtml));
        $this->assertSame($rows($staffHtml), $rows($guardianHtml));
    }

    public function test_the_admin_edits_report_rules_and_the_principal_reads_them(): void
    {
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $values = ['min_attendance_pct' => '85', 'days_absent_counts' => 'absent_only', 'personality_shown' => 'latest_term', 'examination_centre' => 'YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL'];

        $this->actingAs($admin->user)->put('/admin/report-rules', ['values' => $values, 'confirmed' => ['min_attendance_pct']])->assertSessionHas('status');
        $this->assertSame(85, ReportRule::minAttendancePct());
        $this->assertNull(ReportRule::where('key', 'min_attendance_pct')->value('pending_question'));
        $this->assertSame('Q8', ReportRule::where('key', 'days_absent_counts')->value('pending_question'));
        $log = AuditLog::where('action', 'Updated report card rules')->sole();
        $this->assertSame(['from' => '80', 'to' => '85'], $log->details['changes']['min_attendance_pct']['value']);

        $this->actingAs($admin->user)->put('/admin/report-rules', ['values' => ['min_attendance_pct' => '0'] + $values])->assertSessionHasErrors('values.min_attendance_pct');
        $this->actingAs($this->principal->user)->get('/principal/report-rules')->assertOk()->assertSee('85%')->assertDontSee('Save rules');
        $this->actingAs($this->principal->user)->put('/admin/report-rules', ['values' => $values])->assertForbidden();
    }
}
