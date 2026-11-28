<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\ReportRule;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\StatementOfMarks;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Services\ResultFreezeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 7 (sdp_doc/YASIS_Academic_and_Fee_Rules.md §7.2): the Statement of Marks, made from the
 * frozen year result, prepared by the Registrar and printed under the Principal's issuance control.
 * Test students have invented names.
 */
class StatementOfMarksTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $registrar;

    private StaffProfile $principal;

    private const KG_FOOTER = 'These examinations are conducted by YAS, Myanmar Union of Seventh-day Adventists operated by the Seventh-day Adventists. This mark list is awarded upon completing all subjects mentioned with a minimum of 40% marks in each subject. Classes are awarded on the following basis: 40-60% aggregate: "Third Class," 61-80% "Second Class," 81-94 % "First," and 95 and above "Distinction."';

    private const NOTE = 'Using the conversion tables above, multiply the equivalent grade value (that matches the score/letter grade) with corresponding credit HRS (Hours) of each course to get the total grade points. It will then divide the total number of grade points by the total number of credit hours which will be resulted in the Grade Point Average (GPA), and awarded the Class of Pass (CP).';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->registrar = $this->makeStaff('registrar', 'Registrar', 'registrar@test.local');
        $this->principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        SystemSetting::set('transcript_issuance_enabled', '1');
    }

    // --- Fixtures -------------------------------------------------------------------------------------

    /** The rules file's KG-4 example (Grade 2): the same marks in every term, so the year mark is the same. */
    private function kgStudent(array $overrides = [], string $name = 'Mya Kyi Phyu'): Student
    {
        $section = Section::where('name', 'Grade 2-A')->first() ?? $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil($name, $section);
        $student->update(['date_of_birth' => '2016-01-25']);
        foreach ($this->terms as $i => $term) {
            $this->setMarks($student, $section, $term, $i === 3 ? array_merge(self::KG_EXAMPLE, $overrides) : self::KG_EXAMPLE);
        }

        return $student;
    }

    /** The rules file's Grade 12 example: a science combination with the same marks every term. */
    private function g12Student(): Student
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->terms[0], 'Hla Win Naing');
        foreach (array_slice($this->terms, 1) as $term) {
            $this->setMarks($student, $section, $term, self::G12_EXAMPLE);
            $this->setWord($student, $section, $term, 'Pass');
        }

        return $student;
    }

    private function lockTerms(int $count = 4): void
    {
        foreach (array_slice($this->terms, 0, $count) as $term) {
            app(ResultFreezeService::class)->lock($term, $this->principal->user);
        }
    }

    private function prepare(Student $student, array $overrides = [])
    {
        return $this->actingAs($this->registrar->user)->post('/registrar/statements-of-marks', array_merge([
            'student_id_number' => $student->student_id_number, 'academic_year_id' => $this->year->id,
            'examination_date' => 'March 2-5, 2027', 'issue_date' => '2027-03-26',
        ], $overrides));
    }

    /** The HTML the PDF is made from, for a print (POST) or a preview (GET). */
    private function pdfHtml(string $url, string $method = 'post'): string
    {
        $html = null;
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use (&$html) {
            $html = view($view, $data)->render();
            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('setPaper')->with('a4', 'portrait')->andReturnSelf();
            $pdf->shouldReceive('stream')->andReturn(response('%PDF', 200, ['Content-Type' => 'application/pdf']));

            return $pdf;
        });
        $this->actingAs($this->registrar->user)->{$method}($url)->assertOk();

        return $html;
    }

    private function text(string $html): string
    {
        $html = preg_replace(['#<style.*?</style>#s', '/<[^>]+>/'], ['', ' '], $html);

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($html, ENT_QUOTES | ENT_HTML5)));
    }

    // --- The two layouts -------------------------------------------------------------------------------

    public function test_the_kg_example_prints_its_counted_subjects_accumulative_score_and_class(): void
    {
        $student = $this->kgStudent();
        $this->lockTerms();

        $this->prepare($student)->assertSessionHas('status');
        $statement = StatementOfMarks::sole();
        $this->assertSame(['kg9', 'Draft'], [$statement->layout, $statement->documentRequest->status]);

        $text = $this->text($this->pdfHtml("/registrar/statements-of-marks/{$statement->id}/print"));
        $this->assertStringContainsString('THE DEPARTMENT OF EDUCATION Myanmar Union Mission of Seventh-day Adventists Yangon, MYANMAR STATEMENT OF MARKS', $text);
        $this->assertStringContainsString('NAME: MYA KYI PHYU GRADE: 2 DATE OF BIRTH: JAN 25, 2016', $text);
        $this->assertStringContainsString('EXAMINATION CENTRE: YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL DATE OF EXAMINATION: MARCH 2-5, 2027 DATE OF ISSUE: MARCH 26, 2027', $text);
        // The first four subjects on the left, the rest on the right: Character Building counts and is
        // listed; Social Study doesn't count and isn't (Q4).
        $this->assertStringContainsString('COMPULSORY: COMPULSORY: 90 MORAL/VALUES EDU 89 SCIENCE 94 MYANMAR 90 MAPE 94 ENGLISH 91 CHARACTER BUILDING 90 MATHEMATICS 82', $text);
        $this->assertStringNotContainsString('SOCIAL STUDY', $text);
        $this->assertStringContainsString('RESULT PASS CLASS OF PASS FIRST', $text);
        $this->assertStringContainsString(self::KG_FOOTER, $text);
        $this->assertStringContainsString('Registrar Principal', $text);
        $this->assertStringNotContainsString('Principal Test User', $text); // the signatures stay manual

        $statement->refresh();
        $this->assertSame('Printed', $statement->documentRequest->status);
        $this->assertNotNull($statement->issued_at);
        $this->assertSame(['Prepared statement of marks', 'Issued statement of marks'],
            AuditLog::where('entity_type', 'DocumentRequest')->orderBy('id')->pluck('action')->all());
        $this->assertSame('registrar', AuditLog::where('action', 'Issued statement of marks')->sole()->role);
    }

    public function test_the_grade_12_example_prints_letters_hours_options_gpa_and_the_schemes_conversion_table(): void
    {
        $student = $this->g12Student();
        $this->lockTerms();
        $this->prepare($student)->assertSessionHas('status');

        $text = $this->text($this->pdfHtml('/registrar/statements-of-marks/'.StatementOfMarks::sole()->id.'/print'));
        $this->assertStringContainsString('SUBJECT SCORE HRS SUBJECT SCORE HRS GPA COMPULSORY: OPTIONAL:', $text);
        $this->assertStringContainsString('MORAL/VALUES EDU B 3 BIOLOGY B 2', $text);
        $this->assertStringContainsString('MYANMAR C+ 3 CHEMISTRY B- 2', $text);
        $this->assertStringContainsString('MATHEMATICS C+ 3 WORLD HISTORY - 2', $text);
        $this->assertStringContainsString('PE A 1', $text);
        $this->assertStringContainsString('SUPW PASS -', $text);
        $this->assertStringContainsString('3.04', $text);
        $this->assertStringContainsString('RESULT PASS CLASS OF PASS SECOND', $text);

        // The conversion table comes from the scheme's bands, D+ included; the class ranges under it.
        $this->assertStringContainsString('How are the accumulated GPA calculated and CP grouped?', $text);
        $this->assertStringContainsString('Score/Letter Grade A A- B+ B B- C+ C C- D+ D F', $text);
        $this->assertStringContainsString('Grade Value 4 3.67 3.33 3 2.67 2.33 2 1.67 1.33 1 0', $text);
        $this->assertStringContainsString('(3) HRS 12 11.01 9.99 9 8.01 6.99 6 5.01 3.99 3 0', $text);
        $this->assertStringContainsString('(2) HRS 8 7.34 6.66 6 5.34 4.66 4 3.34 2.66 2 0', $text);
        $this->assertStringContainsString('Class of Pass Distinction First Second Third Fail GPA 3.68-4.00 3.33-3.67 2.68-3.32 2.00-2.67 below 2.00', $text);
        $this->assertStringContainsString('Note: '.self::NOTE, $text);
    }

    public function test_a_student_who_failed_gets_a_statement_with_fail_and_no_class(): void
    {
        $failing = $this->kgStudent([], 'Aye Chan Moe');
        foreach ($this->terms as $term) {
            $this->setMark($failing, Section::where('name', 'Grade 2-A')->first(), 'MATH', $term, 30);
        }
        $this->lockTerms();

        $this->prepare($failing)->assertSessionHas('status');
        $text = $this->text($this->pdfHtml('/registrar/statements-of-marks/'.StatementOfMarks::sole()->id.'/print'));
        $this->assertStringContainsString('MATHEMATICS 30', $text);
        $this->assertStringContainsString('RESULT FAIL CLASS OF PASS —', $text);
    }

    // --- When there is no statement ---------------------------------------------------------------------

    public function test_no_statement_until_every_term_is_locked(): void
    {
        $student = $this->kgStudent();
        $this->lockTerms(3);

        $this->prepare($student)->assertSessionHas('warning', 'The Statement of Marks is made from the frozen year result, once the Principal has locked every term of 2026-2027. Not locked yet: Final.');
        $this->assertSame(0, DocumentRequest::count());
    }

    public function test_grade_10_has_no_statement_until_the_school_answers_q11(): void
    {
        $student = $this->pupil('Ko Ten', $this->levelSection('Grade 10-A', '10'));
        $this->lockTerms();

        $this->prepare($student)->assertSessionHas('warning', 'No Statement of Marks for Grade 10 until the school answers Q11 (whether it issues one, and how the DHSC result is recorded).');
        $this->assertSame(0, StatementOfMarks::count());
    }

    public function test_an_incomplete_or_withdrawn_year_gets_no_statement_with_the_reason(): void
    {
        $missing = $this->kgStudent(['MATH' => null]);
        $withdrawn = $this->kgStudent([], 'Left Early');
        $withdrawn->enrollments()->update(['status' => 'Withdrawn']);
        $this->lockTerms();

        $this->prepare($missing)->assertSessionHas('warning');
        $this->assertStringContainsString("Mya Kyi Phyu's 2026-2027 result is not complete, so there is no Statement of Marks:", session('warning'));
        $this->prepare($withdrawn)->assertSessionHas('warning', 'Left Early withdrew during 2026-2027, so there is no year result and no Statement of Marks.');
        $this->assertSame(0, StatementOfMarks::count());
    }

    // --- Issuing ----------------------------------------------------------------------------------------

    public function test_printing_waits_for_the_principals_issuance_control_but_the_draft_can_be_previewed(): void
    {
        SystemSetting::set('transcript_issuance_enabled', '0');
        $student = $this->kgStudent();
        $this->lockTerms();
        $this->prepare($student);
        $statement = StatementOfMarks::sole();

        $this->actingAs($this->registrar->user)->get('/registrar/documents')->assertOk()
            ->assertSee('Statements of Marks print once the Principal turns on')->assertSee('Print (issue)');
        $this->actingAs($this->registrar->user)->post("/registrar/statements-of-marks/{$statement->id}/print")->assertSessionHas('warning');
        $this->assertNull($statement->fresh()->issued_at);

        $preview = $this->pdfHtml("/registrar/statements-of-marks/{$statement->id}/preview", 'get');
        $this->assertStringContainsString('DRAFT – NOT ISSUED', $preview);
        $this->assertSame('Draft', $statement->documentRequest->fresh()->status);

        SystemSetting::set('transcript_issuance_enabled', '1');
        $this->assertStringNotContainsString('DRAFT', $this->pdfHtml("/registrar/statements-of-marks/{$statement->id}/print"));
    }

    public function test_an_issued_statement_never_changes_and_a_refreeze_is_flagged(): void
    {
        $student = $this->kgStudent();
        $this->lockTerms();
        $this->prepare($student);
        $statement = StatementOfMarks::sole();
        $this->pdfHtml("/registrar/statements-of-marks/{$statement->id}/print");

        // The rule and the results change afterwards (an approved grade change re-freezes the year).
        ReportRule::where('key', 'examination_centre')->update(['value' => 'YANGON ADVENTIST SEMINARY']);
        $freezes = app(ResultFreezeService::class);
        $freezes->unlock($this->terms[3], $this->principal->user);
        $this->setMark($student, Section::where('name', 'Grade 2-A')->first(), 'MATH', $this->terms[3], 100);
        $freezes->lock($this->terms[3], $this->principal->user);

        $text = $this->text($this->pdfHtml("/registrar/statements-of-marks/{$statement->id}/print"));
        $this->assertStringContainsString('EXAMINATION CENTRE: YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL', $text);
        $this->assertStringContainsString('MATHEMATICS 82', $text);
        $this->assertSame(1, AuditLog::where('action', 'Re-printed statement of marks')->count());

        $this->actingAs($this->registrar->user)->get('/registrar/documents')->assertOk()
            ->assertSee('re-frozen after this was issued', false)->assertSee('Re-print');
        $this->actingAs($this->registrar->user)->put("/registrar/statements-of-marks/{$statement->id}", ['examination_date' => 'April 2027', 'issue_date' => '2027-04-01'])
            ->assertSessionHas('warning');

        // A new statement takes the new results and the new centre.
        $this->prepare($student)->assertSessionHas('status');
        $new = StatementOfMarks::latest('id')->first();
        $text = $this->text($this->pdfHtml("/registrar/statements-of-marks/{$new->id}/print"));
        $this->assertStringContainsString('EXAMINATION CENTRE: YANGON ADVENTIST SEMINARY DATE OF EXAMINATION', $text);
        $this->assertStringContainsString('MATHEMATICS 87', $text); // (82 * 3 + 100) / 4 = 86.5, so 87
    }

    public function test_a_drafts_dates_change_with_an_audit_and_one_draft_per_student_and_year(): void
    {
        $student = $this->kgStudent();
        $this->lockTerms();
        $this->prepare($student);
        $statement = StatementOfMarks::sole();

        $this->prepare($student)->assertSessionHas('warning', 'Mya Kyi Phyu already has a draft Statement of Marks for 2026-2027. Change its dates there, or print it.');
        $this->actingAs($this->registrar->user)->put("/registrar/statements-of-marks/{$statement->id}", ['examination_date' => 'April 2027', 'issue_date' => '2027-04-02'])
            ->assertSessionHas('status');
        $this->assertSame(['April 2027', '2027-04-02'], [$statement->fresh()->examination_date, $statement->fresh()->issue_date->toDateString()]);
        $log = AuditLog::where('action', 'Changed statement of marks dates')->sole();
        $this->assertSame(['from' => '2027-03-26', 'to' => '2027-04-02'], $log->details['changes']['issue_date']);

        $this->actingAs($this->registrar->user)->post('/registrar/statements-of-marks', ['student_id_number' => 'NOPE'])
            ->assertSessionHasErrors(['student_id_number', 'academic_year_id', 'examination_date', 'issue_date']);
    }

    public function test_the_examination_centre_is_an_admin_rule_confirmed_by_the_school(): void
    {
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $this->assertNull(ReportRule::where('key', 'examination_centre')->value('pending_question')); // Q15 answered
        $this->actingAs($this->principal->user)->get('/principal/report-rules')->assertOk()->assertSee('YANGON ADVENTIST SEMINARY INTERNATIONAL SCHOOL');

        $values = ['min_attendance_pct' => '80', 'personality_shown' => 'final_report', 'supw_incomplete_blocks_promotion' => 'no', 'excused_absences' => 'count_as_absent', 'lates_per_absence' => '3', 'attendance_check_period' => 'year', 'max_subject_absence_pct' => '10', 'subject_attendance_source' => 'daily', 'flag_basis' => 'whole_period', 'school_week' => 'mon_fri'];
        $this->actingAs($admin->user)->put('/admin/report-rules', ['values' => $values + ['examination_centre' => '  YANGON ADVENTIST SEMINARY ']])
            ->assertSessionHas('status');
        $this->assertSame('YANGON ADVENTIST SEMINARY', ReportRule::examinationCentre());
        $this->actingAs($admin->user)->put('/admin/report-rules', ['values' => $values + ['examination_centre' => '']])
            ->assertSessionHasErrors('values.examination_centre');
    }

    public function test_only_the_registrar_prepares_and_prints_and_the_generic_download_is_closed(): void
    {
        $student = $this->kgStudent();
        $this->lockTerms();
        $this->prepare($student);
        $statement = StatementOfMarks::sole();

        $teacher = $this->makeStaff('teacher', 'Teacher', 'teacher@test.local');
        $this->actingAs($teacher->user)->post("/registrar/statements-of-marks/{$statement->id}/print")->assertForbidden();
        $this->actingAs($this->principal->user)->get("/registrar/statements-of-marks/{$statement->id}/preview")->assertForbidden();
        $this->actingAs($this->registrar->user)->get("/registrar/documents/{$statement->document_request_id}/download")->assertNotFound();
        $this->actingAs($this->registrar->user)->post('/registrar/documents', ['student_id_number' => $student->student_id_number, 'type' => 'Statement of Marks'])
            ->assertSessionHasErrors('type');
    }
}
