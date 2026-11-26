<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\GradeChangeRequest;
use App\Models\GradingScheme;
use App\Models\ResultFreeze;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Services\GradingSchemeService;
use App\Services\ResultFreezeService;
use App\Services\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Frozen results: locking a term calculates its results once and stores them with the scheme
 * settings and bands used, so later edits to bands or settings can't change a past term. Unlocking
 * lifts the freeze (kept as history); relocking reuses the first freeze's rules unless the Principal
 * chooses to recalculate with the current rules and says why. A grade change applied to a locked
 * term re-freezes that section with the freeze's own rules.
 */
class ResultFreezeTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $principal;

    private StaffProfile $admin;

    private Section $section;

    private Student $first;

    private Student $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->principal = $this->makeStaff('principal', 'Principal');
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');

        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE'];
        $this->section = $this->levelSection('Grade 2-A', '2');
        // Average 81: First under today's bands (81-94).
        $this->first = $this->pupil('Eighty One', $this->section);
        $this->setMarks($this->first, $this->section, $this->terms[0], array_fill_keys($codes, 81));
        $this->second = $this->pupil('Seventy', $this->section);
        $this->setMarks($this->second, $this->section, $this->terms[0], array_fill_keys($codes, 70));
    }

    private function freezes(): ResultFreezeService
    {
        return app(ResultFreezeService::class);
    }

    private function stored(Student $student)
    {
        return app(ResultService::class)->sectionTerm($this->section, $this->terms[0])->for($student);
    }

    private function live(Student $student)
    {
        return app(ResultService::class)->liveSectionTerm($this->section, $this->terms[0])->for($student);
    }

    /** The Admin moves First up to 82-94, so an average of 81 becomes Second. */
    private function raiseFirstClass(): void
    {
        $bands = [
            ['label' => 'Distinction', 'min' => '95', 'max' => '100', 'value' => '', 'is_fail' => false],
            ['label' => 'First', 'min' => '82', 'max' => '94', 'value' => '', 'is_fail' => false],
            ['label' => 'Second', 'min' => '61', 'max' => '81', 'value' => '', 'is_fail' => false],
            ['label' => 'Third', 'min' => '40', 'max' => '60', 'value' => '', 'is_fail' => false],
            ['label' => 'Fail', 'min' => '0', 'max' => '39', 'value' => '', 'is_fail' => true],
        ];
        app(GradingSchemeService::class)->saveBands(GradingScheme::where('code', 'KG_9')->sole(), 'result', ['decimals' => 0, 'bands' => $bands], $this->admin->user);
    }

    private function governance(array $fields)
    {
        return $this->actingAs($this->principal->user)->put('/principal/governance', array_merge([
            'promotion_window_open' => '1', 'transcript_issuance_enabled' => '0', 'principal_may_assist_registration' => '1',
        ], $fields));
    }

    public function test_locking_a_term_stores_its_results_and_later_band_edits_do_not_change_them(): void
    {
        $freeze = $this->freezes()->lock($this->terms[0], $this->principal->user);

        $this->assertTrue($this->terms[0]->fresh()->is_locked);
        $this->assertSame(2, StudentResult::where('result_freeze_id', $freeze->id)->count());
        $this->assertSame(3, $freeze->schemes()->count()); // a copy of every scheme's settings and bands
        $this->assertSame(['81', 'First', 1], [$this->stored($this->first)->value, $this->stored($this->first)->classLabel, $this->stored($this->first)->position]);
        $this->assertTrue($this->stored($this->first)->frozen);

        $this->raiseFirstClass();
        GradingScheme::where('code', 'KG_9')->update(['rounding_mode' => 'down']);

        $this->assertSame(['81', 'First'], [$this->stored($this->first)->value, $this->stored($this->first)->classLabel]);
        $this->assertSame('Second', $this->live($this->first)->classLabel);

        $log = AuditLog::where('action', 'Locked term results')->sole();
        $this->assertSame(['principal', 'Term'], [$log->role, $log->entity_type]);
        $this->assertSame(2, $log->details['students']);
    }

    public function test_unlocking_lifts_the_freeze_and_relocking_reuses_the_first_freezes_rules(): void
    {
        $first = $this->freezes()->lock($this->terms[0], $this->principal->user);
        $this->raiseFirstClass();

        $this->freezes()->unlock($this->terms[0], $this->principal->user);
        $this->assertFalse($this->terms[0]->fresh()->is_locked);
        $this->assertNotNull($first->fresh()->lifted_at);
        $this->assertSame(2, StudentResult::where('result_freeze_id', $first->id)->count()); // kept as history
        // Unlocked: calculated live, with today's bands.
        $this->assertFalse($this->stored($this->first)->frozen);
        $this->assertSame('Second', $this->stored($this->first)->classLabel);

        $again = $this->freezes()->lock($this->terms[0], $this->principal->user);
        $this->assertSame('first_freeze', $again->rules);
        $this->assertSame('First', $this->stored($this->first)->classLabel);
        $this->assertEquals(
            $first->schemes()->orderBy('grading_scheme_id')->get()->map->only(['grading_scheme_id', 'settings', 'mark_bands', 'result_bands'])->all(),
            $again->schemes()->orderBy('grading_scheme_id')->get()->map->only(['grading_scheme_id', 'settings', 'mark_bands', 'result_bands'])->all(),
        );
        $this->assertSame(1, AuditLog::where('action', 'Unlocked term results')->count());
    }

    public function test_the_principal_can_relock_with_the_current_rules_with_a_written_reason(): void
    {
        $this->governance(['grade_lock' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('First', $this->stored($this->first)->classLabel);
        $this->raiseFirstClass();
        $this->governance(['grade_lock' => '0'])->assertSessionHasNoErrors();

        // The option needs a reason.
        $this->governance(['grade_lock' => '1', 'relock_rules' => 'current', 'relock_reason' => ''])
            ->assertSessionHasErrors('relock_reason');
        $this->assertFalse($this->terms[0]->fresh()->is_locked);

        $this->governance(['grade_lock' => '1', 'relock_rules' => 'current', 'relock_reason' => 'The school answered Q10: First starts at 82.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Second', $this->stored($this->first)->classLabel);
        $freeze = ResultFreeze::whereNull('lifted_at')->where('term_id', $this->terms[0]->id)->sole();
        $this->assertSame(['current', 'The school answered Q10: First starts at 82.'], [$freeze->rules, $freeze->reason]);

        $log = AuditLog::where('action', 'Locked term results')->latest('id')->first();
        $this->assertSame('current', $log->details['rules']);
        $this->assertSame('The school answered Q10: First starts at 82.', $log->details['reason']);
        $this->assertContains('First 81-94', $log->details['snapshot_before']['KG_9']['result']);
        $this->assertContains('First 82-94', $log->details['snapshot_after']['KG_9']['result']);
    }

    public function test_the_relock_option_is_offered_only_for_a_term_that_was_locked_before(): void
    {
        $this->actingAs($this->principal->user)->get('/principal/governance')->assertOk()
            ->assertDontSee('Recalculate with the current rules');

        $this->freezes()->lock($this->terms[0], $this->principal->user);
        $this->freezes()->unlock($this->terms[0], $this->principal->user);

        $this->actingAs($this->principal->user)->get('/principal/governance')->assertOk()
            ->assertSee('Recalculate with the current rules')
            ->assertSee('Use the rules from the first lock');
    }

    public function test_a_grade_change_applied_to_a_locked_term_refreezes_the_section_with_the_freezes_rules(): void
    {
        $freeze = $this->freezes()->lock($this->terms[0], $this->principal->user);
        $this->raiseFirstClass(); // must not reach the locked term

        $vp = $this->makeStaff('vp_academic', 'VP Academic');
        $teacher = $this->makeStaff('teacher', 'Teacher');
        $mathId = Subject::where('code', 'MATH')->whereNull('department_id')->value('id');
        $assessment = Assessment::whereHas('category', fn ($q) => $q->where('section_id', $this->section->id)->where('subject_id', $mathId)->where('term_id', $this->terms[0]->id))->sole();
        // Seventy's Math goes 70 -> 100: average 75, still Second; positions unchanged. Eighty One's Math 81 -> 99: average 84.
        $request = GradeChangeRequest::create([
            'assessment_id' => $assessment->id, 'student_id' => $this->first->id, 'term_id' => $this->terms[0]->id,
            'old_score' => 81, 'new_score' => 99, 'reason' => 'Marking error', 'status' => 'VP_Approved',
            'requested_by' => $teacher->id, 'vp_approved_by' => $vp->id, 'vp_approved_at' => now(),
        ]);

        $this->actingAs($this->principal->user)->post("/principal/approvals/grade-changes/{$request->id}/approve")
            ->assertSessionHas('status');

        $r = $this->stored($this->first);
        $this->assertSame(['84', 'First', true], [$r->value, $r->classLabel, $r->frozen]); // the freeze's bands, not today's
        $this->assertSame(2, StudentResult::where('result_freeze_id', $freeze->id)->whereNotNull('superseded_at')->count());
        $this->assertSame(2, StudentResult::where('result_freeze_id', $freeze->id)->whereNull('superseded_at')->count());
        $this->assertStringContainsString("Grade change #{$request->id}",
            StudentResult::where('result_freeze_id', $freeze->id)->whereNotNull('superseded_at')->value('superseded_reason'));
        $this->assertSame(1, AuditLog::where('action', 'Re-froze section results')->count());
    }

    public function test_the_year_is_frozen_when_its_last_term_is_locked_and_lifted_with_any_term(): void
    {
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE'];
        foreach (array_slice($this->terms, 1) as $term) {
            $this->setMarks($this->first, $this->section, $term, array_fill_keys($codes, 81));
            $this->setMarks($this->second, $this->section, $term, array_fill_keys($codes, 70));
        }

        foreach (array_slice($this->terms, 0, 3) as $term) {
            $this->freezes()->lock($term, $this->principal->user);
        }
        $this->assertSame(0, ResultFreeze::whereNull('term_id')->count());

        $this->freezes()->lock($this->terms[3], $this->principal->user);
        $year = ResultFreeze::whereNull('term_id')->whereNull('lifted_at')->sole();
        $r = app(ResultService::class)->sectionYear($this->section)->for($this->first);
        $this->assertSame(['81', 'First', 'Promote', true], [$r->value, $r->classLabel, $r->recommendation, $r->frozen]);

        $this->freezes()->unlock($this->terms[1], $this->principal->user);
        $this->assertNotNull($year->fresh()->lifted_at);
        $this->assertFalse(app(ResultService::class)->sectionYear($this->section)->for($this->first)->frozen);
    }

    public function test_the_command_freezes_terms_that_were_locked_before_this_release(): void
    {
        $this->terms[0]->update(['is_locked' => true]); // locked the old way, with no freeze

        // Until it is frozen, the term falls back to a live calculation and says so.
        $r = $this->stored($this->first);
        $this->assertFalse($r->frozen);
        $this->assertTrue(app(ResultService::class)->sectionTerm($this->section, $this->terms[0])->lockedWithoutFreeze);

        $this->assertSame(1, Artisan::call('results:freeze-locked-terms', ['--actor' => 'nobody@test.local'])); // unknown actor: refused
        $this->assertSame(0, ResultFreeze::count());

        $this->assertSame(0, Artisan::call('results:freeze-locked-terms', ['--actor' => 'principal@test.local']));
        $this->assertStringContainsString('Froze 1 term', Artisan::output());
        $this->assertTrue($this->stored($this->first)->frozen);
        $this->assertSame('principal', AuditLog::where('action', 'Locked term results')->sole()->role);

        Artisan::call('results:freeze-locked-terms', ['--actor' => 'principal@test.local']);
        $this->assertStringContainsString('Nothing to freeze', Artisan::output());
        $this->assertSame(1, ResultFreeze::count());
    }

    public function test_the_governance_lock_toggle_freezes_and_unfreezes(): void
    {
        $this->governance(['grade_lock' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, ResultFreeze::whereNull('lifted_at')->count());

        // Saving the other controls again does not freeze a second time.
        $this->governance(['grade_lock' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, ResultFreeze::count());

        $this->governance(['grade_lock' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(0, ResultFreeze::whereNull('lifted_at')->count());
    }
}
