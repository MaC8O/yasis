<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GradeLevel;
use App\Models\GradingScheme;
use App\Models\GradingSchemeBand;
use App\Models\StaffProfile;
use App\Support\GradingBands;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 3 of the academic rules (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4-6): grading schemes
 * per grade level, seeded by migration, edited by the Admin with bands that cover the whole range
 * with no gaps or overlaps, archived (never deleted) when replaced, and shown read-only to the
 * Principal.
 */
class GradingSchemeTest extends TestCase
{
    use RefreshDatabase, SeedsCoreData;

    private StaffProfile $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedDepartment();
        $this->seedAcademicCalendar();
        $this->admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
    }

    private function scheme(string $code): GradingScheme
    {
        return GradingScheme::where('code', $code)->sole();
    }

    /** The active bands of a set as [label, min, max, value, is_fail], highest first. */
    private function bands(string $code, string $kind): array
    {
        return $this->scheme($code)->activeBands($kind)->get()
            ->map(fn ($b) => [$b->label, (string) $b->min, (string) $b->max, $b->value === null ? null : (string) $b->value, $b->is_fail])
            ->all();
    }

    /** A band set as the edit form posts it. */
    private function payload(array $rows, int $decimals = 0, array $extra = []): array
    {
        return array_merge([
            '_set' => 'result',
            'decimals' => $decimals,
            'bands' => array_map(fn ($r) => ['label' => $r[0], 'min' => $r[1], 'max' => $r[2], 'value' => $r[3] ?? '', 'is_fail' => ($r[4] ?? false) ? '1' : null], $rows),
        ], $extra);
    }

    private const KG9_RESULT = [
        ['Distinction', '95', '100'], ['First', '81', '94'], ['Second', '61', '80'], ['Third', '40', '60'], ['Fail', '0', '39', null, true],
    ];

    private function asAdmin()
    {
        return $this->actingAs($this->admin->user);
    }

    // --- Seeded by migration -------------------------------------------------------------------

    public function test_migration_seeds_the_three_schemes_from_sections_4_to_6(): void
    {
        $this->assertSame(['KG_9', 'G10', 'G11_12'], GradingScheme::orderBy('id')->pluck('code')->all());

        $kg = $this->scheme('KG_9');
        // Q20 (how failed students are ranked) was added to every scheme by a later migration.
        $this->assertSame(['average', '100.00', 0, 0, 'half_up', 'any_counted', 'together', 'Q10,Q20'],
            [$kg->result_basis, (string) $kg->result_max, $kg->mark_decimals, $kg->result_decimals, $kg->rounding_mode, $kg->subject_fail_rule, $kg->failed_ranking, $kg->pending_question]);
        $this->assertSame([['PASS', '40.00', '100.00', null, false], ['FAIL', '0.00', '39.00', null, true]], $this->bands('KG_9', 'mark'));
        $this->assertSame([
            ['Distinction', '95.00', '100.00', null, false], ['First', '81.00', '94.00', null, false], ['Second', '61.00', '80.00', null, false],
            ['Third', '40.00', '60.00', null, false], ['Fail', '0.00', '39.00', null, true],
        ], $this->bands('KG_9', 'result'));

        $g10 = $this->scheme('G10');
        // Q9 (does the fixed gradebook template apply to Grade 10 and 11-12?) was added in phase 5.
        $this->assertSame(['point_total', '70.00', 0, 'any_counted', 'Q9,Q10,Q19,Q20'],
            [$g10->result_basis, (string) $g10->result_max, $g10->result_decimals, $g10->subject_fail_rule, $g10->pending_question]);
        $this->assertSame([
            ['A', '85.00', '100.00', '10.00', false], ['B', '76.00', '84.00', '9.00', false], ['C+', '67.00', '75.00', '8.00', false],
            ['C', '58.00', '66.00', '7.00', false], ['C-', '49.00', '57.00', '6.00', false], ['D', '40.00', '48.00', '5.00', false],
            ['Fail', '0.00', '39.00', '0.00', true],
        ], $this->bands('G10', 'mark'));
        $this->assertSame([
            ['Distinction', '66.00', '70.00', null, false], ['First', '56.00', '65.00', null, false], ['Second', '46.00', '55.00', null, false],
            ['Third', '40.00', '45.00', null, false], ['Fail', '0.00', '39.00', null, true],
        ], $this->bands('G10', 'result'));
        // The Grade 10 defaults below 40 marks and below 40 points wait on Q19.
        $this->assertSame(['Fail'], $this->scheme('G10')->activeBands('mark')->whereNotNull('pending_question')->pluck('label')->all());
        $this->assertSame(['Q19', 'Q19'], GradingSchemeBand::active()->whereNotNull('pending_question')->pluck('pending_question')->all());

        $gpa = $this->scheme('G11_12');
        $this->assertSame(['gpa', '4.00', 0, 2, 'any_compulsory', 'Q9,Q10,Q20'],
            [$gpa->result_basis, (string) $gpa->result_max, $gpa->mark_decimals, $gpa->result_decimals, $gpa->subject_fail_rule, $gpa->pending_question]);
        $this->assertSame([
            ['A', '90.00', '100.00', '4.00', false], ['A-', '85.00', '89.00', '3.67', false], ['B+', '80.00', '84.00', '3.33', false],
            ['B', '75.00', '79.00', '3.00', false], ['B-', '70.00', '74.00', '2.67', false], ['C+', '65.00', '69.00', '2.33', false],
            ['C', '60.00', '64.00', '2.00', false], ['C-', '55.00', '59.00', '1.67', false], ['D+', '50.00', '54.00', '1.33', false],
            ['D', '40.00', '49.00', '1.00', false], ['F', '0.00', '39.00', '0.00', true],
        ], $this->bands('G11_12', 'mark'));
        $this->assertSame([
            ['Distinction', '3.68', '4.00', null, false], ['First', '3.33', '3.67', null, false], ['Second', '2.68', '3.32', null, false],
            ['Third', '2.00', '2.67', null, false], ['Fail', '0.00', '1.99', null, true],
        ], $this->bands('G11_12', 'result'));
    }

    public function test_every_seeded_band_set_is_valid_by_the_admins_own_rules(): void
    {
        $service = app(\App\Services\GradingSchemeService::class);
        foreach (GradingScheme::all() as $scheme) {
            foreach (['mark', 'result'] as $kind) {
                $bands = $scheme->activeBands($kind)->get()->map(fn ($b) => ['label' => $b->label, 'min' => $b->min, 'max' => $b->max, 'value' => $b->value, 'is_fail' => $b->is_fail])->all();
                $this->assertSame([], $service->problems($scheme, $kind, $bands, $scheme->decimals($kind), (string) $scheme->result_max), "{$scheme->code} {$kind}");
            }
        }
    }

    public function test_every_school_grade_level_is_assigned_its_scheme(): void
    {
        $assigned = GradeLevel::ordered()->with('gradingScheme')->get()->mapWithKeys(fn ($l) => [$l->code => $l->gradingScheme?->code])->all();

        $this->assertSame([
            'KG1' => 'KG_9', 'KG2' => 'KG_9', '1' => 'KG_9', '2' => 'KG_9', '3' => 'KG_9', '4' => 'KG_9', '5' => 'KG_9',
            '6' => 'KG_9', '7' => 'KG_9', '8' => 'KG_9', '9' => 'KG_9', '10' => 'G10', '11' => 'G11_12', '12' => 'G11_12',
        ], $assigned);
    }

    // --- Band set rules ------------------------------------------------------------------------

    public function test_band_rules_refuse_gaps_overlaps_short_ranges_and_values_off_the_grid(): void
    {
        $check = fn (array $rows, int $decimals = 0, string $top = '100', ?string $valueLabel = null) => GradingBands::problems(
            array_map(fn ($r) => ['label' => $r[0], 'min' => $r[1], 'max' => $r[2], 'value' => $r[3] ?? '', 'is_fail' => $r[4] ?? false], $rows),
            $decimals, $top, $valueLabel,
        );

        $this->assertSame([], $check(self::KG9_RESULT));
        $this->assertSame(['There is a gap between “Second” (up to 80) and “First” (from 82).'],
            $check([['Distinction', '95', '100'], ['First', '82', '94'], ['Second', '61', '80'], ['Third', '40', '60'], ['Fail', '0', '39', null, true]]));
        $this->assertSame(['“Second” and “First” overlap: 80 to 80 is in both.'],
            $check([['Distinction', '95', '100'], ['First', '80', '94'], ['Second', '61', '80'], ['Third', '40', '60'], ['Fail', '0', '39', null, true]]));
        $this->assertSame(['The bands must start at 0. The lowest band, “Fail”, starts at 1.'],
            $check([['Pass', '40', '100'], ['Fail', '1', '39', null, true]]));
        $this->assertSame(['The bands must reach 100. The highest band, “Pass”, stops at 99.'],
            $check([['Pass', '40', '99'], ['Fail', '0', '39', null, true]]));
        $this->assertSame(['“Pass”: use whole numbers. This scheme rounds to whole numbers before the band lookup.'],
            $check([['Pass', '39.5', '100'], ['Fail', '0', '39', null, true]]));
        $this->assertSame(['“Fail”: goes above 100, the top of the range.'],
            $check([['Pass', '40', '100'], ['Fail', '0', '139', null, true]]));
        $this->assertSame(['“Low” is a fail band above a passing band. Fail bands must be the lowest.'],
            $check([['Low', '60', '100', null, true], ['Mid', '40', '59'], ['Fail', '0', '39', null, true]]));
        $this->assertSame(['Mark the lowest band as a fail band. A set needs at least one.'],
            $check([['Pass', '40', '100'], ['Low', '0', '39']]));
        $this->assertSame(['Two bands are labelled “pass”. Each label must be different.'],
            $check([['Pass', '40', '100'], ['pass', '0', '39', null, true]]));
        $this->assertSame(['A band set needs at least two bands: one that passes and one that fails.'],
            $check([['Pass', '0', '100']]));

        // GPA grid: 0.01 steps.
        $this->assertSame([], $check([['Pass', '2.00', '4.00'], ['Fail', '0.00', '1.99', null, true]], 2, '4.00'));
        $this->assertSame(['There is a gap between “Fail” (up to 1.98) and “Pass” (from 2).'],
            $check([['Pass', '2.00', '4.00'], ['Fail', '0.00', '1.98', null, true]], 2, '4.00'));

        // Points: required, and never higher for a lower band.
        $this->assertSame(['“A” needs its points, a number such as 10 or 3.67.'],
            $check([['A', '40', '100', ''], ['F', '0', '39', '0', true]], 0, '100', 'Points'));
        $this->assertSame(['“A” is worth less (5) than the lower band “F” (6). A higher band can\'t be worth less.'],
            $check([['A', '40', '100', '5'], ['F', '0', '39', '6', true]], 0, '100', 'Points'));
        $this->assertSame(['“PASS”: this band set has no points or grade values. Leave the value empty.'],
            $check([['PASS', '40', '100', '3'], ['FAIL', '0', '39', '', true]]));
    }

    // --- Admin edits ----------------------------------------------------------------------------

    public function test_saving_a_band_set_archives_the_old_set_and_audits_both(): void
    {
        $scheme = $this->scheme('KG_9');
        $old = $scheme->activeBands('result')->pluck('id');
        Carbon::setTestNow('2027-01-10 09:00:00');

        $rows = self::KG9_RESULT;
        $rows[1] = ['First', '80', '94'];
        $rows[2] = ['Second', '61', '79'];
        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload($rows))
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(['First', '80.00', '94.00', null, false], $this->bands('KG_9', 'result')[1]);
        // Nothing deleted: the old rows are archived at the moment the new ones start.
        $this->assertSame(5, GradingSchemeBand::whereIn('id', $old)->whereNotNull('archived_at')->count());
        $this->assertSame(12, GradingSchemeBand::where('grading_scheme_id', $scheme->id)->count());
        $newSet = $scheme->activeBands('result')->get();
        $this->assertTrue($newSet->every(fn ($b) => $b->created_at->equalTo(Carbon::parse('2027-01-10 09:00:00'))));

        // Phase 4 finds the set in force when a term was locked.
        $before = GradingSchemeBand::where('grading_scheme_id', $scheme->id)->where('kind', 'result')->inForceAt(Carbon::parse('2027-01-09'))->pluck('id');
        $after = GradingSchemeBand::where('grading_scheme_id', $scheme->id)->where('kind', 'result')->inForceAt(Carbon::parse('2027-01-11'))->pluck('id');
        $this->assertEqualsCanonicalizing($old->all(), $before->all());
        $this->assertEqualsCanonicalizing($newSet->pluck('id')->all(), $after->all());

        $log = AuditLog::where('action', 'Updated grading scheme bands')->sole();
        $this->assertSame('admin', $log->role);
        $this->assertSame('GradingScheme', $log->entity_type);
        $this->assertSame('result', $log->details['set']);
        $this->assertContains('First 81-94', $log->details['before']);
        $this->assertContains('First 80-94', $log->details['after']);
        Carbon::setTestNow();
    }

    public function test_an_invalid_band_set_is_refused_and_nothing_changes(): void
    {
        $scheme = $this->scheme('KG_9');
        $rows = self::KG9_RESULT;
        $rows[1] = ['First', '82', '94'];

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload($rows))
            ->assertSessionHasErrors(['bands' => 'There is a gap between “Second” (up to 80) and “First” (from 82).']);

        $this->assertSame(7, GradingSchemeBand::where('grading_scheme_id', $scheme->id)->count());
        $this->assertSame(0, AuditLog::where('action', 'Updated grading scheme bands')->count());
    }

    public function test_saving_an_unchanged_set_is_refused(): void
    {
        $scheme = $this->scheme('KG_9');

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload(self::KG9_RESULT))
            ->assertSessionHas('warning', 'Nothing was changed.');
        $this->assertSame(0, AuditLog::where('action', 'Updated grading scheme bands')->count());
    }

    public function test_changing_the_decimal_places_needs_bands_that_fit_them(): void
    {
        $scheme = $this->scheme('KG_9');

        // Whole-number bands leave gaps at one decimal place (80 to 81 misses 80.1-80.9).
        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload(self::KG9_RESULT, 1))
            ->assertSessionHasErrors('bands');
        $this->assertSame(0, $scheme->fresh()->result_decimals);

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload([
            ['Distinction', '94.5', '100'], ['First', '80.5', '94.4'], ['Second', '60.5', '80.4'], ['Third', '40', '60.4'], ['Fail', '0', '39.9', null, true],
        ], 1))->assertSessionHasNoErrors();

        $this->assertSame(1, $scheme->fresh()->result_decimals);
        $this->assertSame(['Distinction', '94.50', '100.00', null, false], $this->bands('KG_9', 'result')[0]);
        $this->assertSame(['from' => 0, 'to' => 1], AuditLog::where('action', 'Updated grading scheme bands')->sole()->details['settings']['result_decimals']);
    }

    public function test_the_point_total_range_top_is_edited_with_its_bands(): void
    {
        $scheme = $this->scheme('G10');
        $rows = [['Distinction', '76', '80'], ['First', '56', '75'], ['Second', '46', '55'], ['Third', '40', '45'], ['Fail', '0', '39', null, true]];

        // The bands must reach the top that is posted with them.
        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload($rows, 0, ['result_max' => '70']))
            ->assertSessionHasErrors(['bands' => '“Distinction”: goes above 70, the top of the range.']);

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload($rows, 0, ['result_max' => '80']))
            ->assertSessionHasNoErrors();
        $this->assertSame('80.00', (string) $scheme->fresh()->result_max);
    }

    public function test_a_flag_stays_on_a_resaved_band_until_the_school_confirms(): void
    {
        $scheme = $this->scheme('G10');
        $rows = [['A', '85', '100', '10'], ['B', '76', '84', '9'], ['C+', '67', '75', '8'], ['C', '58', '66', '7'], ['C-', '49', '57', '6'], ['D', '40', '48', '5'], ['Fail', '0', '39', '0', true]];
        $rows[0][0] = 'A*';

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/mark", array_merge($this->payload($rows), ['_set' => 'mark']))->assertSessionHasNoErrors();
        $this->assertSame('Q19', $scheme->activeBands('mark')->where('label', 'Fail')->value('pending_question'));

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}/bands/mark", array_merge($this->payload($rows), ['_set' => 'mark', 'confirmed' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(0, $scheme->activeBands('mark')->whereNotNull('pending_question')->count());
    }

    public function test_settings_are_edited_and_confirming_clears_the_rounding_flag(): void
    {
        $scheme = $this->scheme('KG_9');

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}", [
            'name' => 'KG1 to Grade 9', 'rounding_mode' => 'down', 'subject_fail_rule' => 'any_counted', 'failed_ranking' => 'failed_last', 'confirmed' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $scheme->refresh();
        $this->assertSame(['down', null], [$scheme->rounding_mode, $scheme->pending_question]);
        $log = AuditLog::where('action', 'Updated grading scheme')->sole();
        $this->assertSame('admin', $log->role);
        $this->assertSame(['from' => 'half_up', 'to' => 'down'], $log->details['changes']['rounding_mode']);
        $this->assertSame(['from' => 'Q10,Q20', 'to' => null], $log->details['changes']['pending_question']);
        $this->assertSame(['from' => 'together', 'to' => 'failed_last'], $log->details['changes']['failed_ranking']);
        $this->assertSame('failed_last', $scheme->failed_ranking);

        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}", ['name' => 'KG1 to Grade 9', 'rounding_mode' => 'down', 'subject_fail_rule' => 'any_counted', 'failed_ranking' => 'failed_last'])
            ->assertSessionHas('warning', 'Nothing was changed.');
        $this->asAdmin()->put("/admin/grading-schemes/{$scheme->id}", ['name' => 'Grade 10', 'rounding_mode' => 'sideways', 'subject_fail_rule' => 'any_counted', 'failed_ranking' => 'alphabetical'])
            ->assertSessionHasErrors(['name', 'rounding_mode', 'failed_ranking']);
    }

    public function test_changing_a_levels_scheme_is_audited_with_before_and_after(): void
    {
        $grade10 = GradeLevel::where('code', '10')->sole();

        $this->asAdmin()->put("/admin/grading-schemes/levels/{$grade10->id}", ['grading_scheme_id' => $this->scheme('KG_9')->id])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Grade 10 now uses KG1 to Grade 9.');

        $this->assertSame('KG_9', $grade10->fresh()->gradingScheme->code);
        $log = AuditLog::where('action', 'Changed grade level grading scheme')->sole();
        $this->assertSame(['admin', 'GradeLevel', $grade10->id], [$log->role, $log->entity_type, $log->entity_id]);
        $this->assertSame(['grade_level' => '10', 'from' => 'G10', 'to' => 'KG_9'], $log->details);

        $this->asAdmin()->put("/admin/grading-schemes/levels/{$grade10->id}", ['grading_scheme_id' => $this->scheme('KG_9')->id])
            ->assertSessionHas('warning', 'Grade 10 already uses KG1 to Grade 9.');
        $this->asAdmin()->put("/admin/grading-schemes/levels/{$grade10->id}", ['grading_scheme_id' => ''])
            ->assertSessionHasErrors('grading_scheme_id');
        $this->assertSame(1, AuditLog::where('action', 'Changed grade level grading scheme')->count());
    }

    public function test_admin_page_shows_the_scheme_its_flags_and_the_editors(): void
    {
        $this->asAdmin()->get('/admin/grading-schemes?scheme=G10')->assertOk()
            ->assertSee('Grade 10')
            ->assertSee('Point total of the counted subjects')
            ->assertSee('Needs confirmation from the school (Q19)')
            ->assertSee('Q9, Q10, Q19, Q20 in the rules file')
            ->assertSee('Position in class')
            ->assertSee('Everyone is ranked together by their result')
            ->assertSee('Edit subject marks bands')
            ->assertSee('Save settings')
            ->assertSeeInOrder(['Kindergarten 1', 'Grade 12']);
    }

    // --- Principal and other roles --------------------------------------------------------------

    public function test_principal_sees_the_schemes_read_only(): void
    {
        $principal = $this->makeStaff('principal', 'Principal');
        $scheme = $this->scheme('G11_12');

        $this->actingAs($principal->user)->get('/principal/grading-schemes?scheme=G11_12')->assertOk()
            ->assertSee('Grades 11 and 12')
            ->assertSee('3.68–4.00')
            ->assertSee('Set by the Admin; shown here read-only.')
            ->assertDontSee('Save settings')
            ->assertDontSee('Edit subject marks bands');

        $this->actingAs($principal->user)->put("/admin/grading-schemes/{$scheme->id}", ['name' => 'X', 'rounding_mode' => 'down', 'subject_fail_rule' => 'none'])->assertForbidden();
        $this->actingAs($principal->user)->put("/admin/grading-schemes/{$scheme->id}/bands/result", $this->payload(self::KG9_RESULT))->assertForbidden();
        $this->assertSame('Grades 11 and 12', $scheme->fresh()->name);
    }

    public function test_setup_and_controls_shows_the_schemes_instead_of_department_scales(): void
    {
        $principal = $this->makeStaff('principal', 'Principal');

        $this->actingAs($principal->user)->get('/principal/governance')->assertOk()
            ->assertSee('Grading schemes')
            ->assertSeeInOrder(['KG1 to Grade 9', 'Grade 10', 'Grades 11 and 12'])
            ->assertSee('95–100')
            ->assertSee('66–70')
            ->assertSee('2.68–3.32')
            ->assertDontSee('Descriptive (no GPA)');
    }

    public function test_other_roles_cannot_open_either_page(): void
    {
        $teacher = $this->makeStaff('teacher', 'Teacher');

        $this->actingAs($teacher->user)->get('/admin/grading-schemes')->assertForbidden();
        $this->actingAs($teacher->user)->get('/principal/grading-schemes')->assertForbidden();
    }
}
