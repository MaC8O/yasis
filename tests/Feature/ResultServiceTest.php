<?php

namespace Tests\Feature;

use App\Models\GradingScheme;
use App\Models\GradingSchemeBand;
use App\Models\SubjectOffering;
use App\Services\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 4 of the academic rules (sdp_doc/YASIS_Academic_and_Fee_Rules.md §4-6, §9): subject marks,
 * letters and values, the average / point total / GPA, class of pass, pass or fail, position and
 * the promotion recommendation, all from the grading schemes and the curriculum. Written before
 * the service.
 */
class ResultServiceTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private ResultService $results;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->results = app(ResultService::class);
    }

    private function term(int $sequence = 1)
    {
        return $this->terms[$sequence - 1];
    }

    // --- The worked examples ------------------------------------------------------------------

    public function test_kg_example_average_90_is_first(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil('Sample Two', $section);
        $this->setMarks($student, $section, $this->term(), self::KG_EXAMPLE);
        // Character Building and Social Study are on the card but not counted (Q4): no mark needed.

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame(['complete', 'average', 'Average', '90', 'First', true],
            [$r->status, $r->basis, $r->resultLabel, $r->value, $r->classLabel, $r->passed]);
        $this->assertSame(['89.00', '89', 'PASS', false, true], [
            $r->subject('MORAL')->mark, $r->subject('MORAL')->roundedMark, $r->subject('MORAL')->bandLabel,
            $r->subject('MORAL')->isFail, $r->subject('MORAL')->counted,
        ]);
        $this->assertFalse($r->subject('CHAR')->counted);
        $this->assertNull($r->subject('CHAR')->mark);
    }

    public function test_grade_12_example_gpa_3_04_is_second(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->term());

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame(['complete', 'gpa', 'GPA', '3.04', 'Second', true],
            [$r->status, $r->basis, $r->resultLabel, $r->value, $r->classLabel, $r->passed]);
        $this->assertSame('3.0439', $r->unrounded);
        $this->assertSame(['C+', '2.33'], [$r->subject('MYAN')->bandLabel, $r->subject('MYAN')->bandValue]);
        $this->assertSame(['A-', '3.67'], [$r->subject('ENG')->bandLabel, $r->subject('ENG')->bandValue]);
        // The options not taken are listed but not taken.
        $this->assertFalse($r->subject('WHIST')->taken);
        $this->assertTrue($r->subject('BIO')->taken);
    }

    public function test_an_elective_student_who_takes_world_history_instead_of_biology(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->term(), 'History Taker', ['BIO' => null, 'WHIST' => 95]);
        $this->choose($student, '12', 'A', 'WHIST');

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        // 70.01 - 6.00 (Biology B) + 8.00 (World History A x 2 hours) = 72.01 over the same 23 hours.
        $this->assertSame(['complete', '3.1309', '3.13'], [$r->status, $r->unrounded, $r->value]);
        $this->assertTrue($r->subject('WHIST')->taken);
        $this->assertTrue($r->subject('WHIST')->counted);
        $this->assertFalse($r->subject('BIO')->taken);
    }

    public function test_supw_is_left_out_of_the_gpa_and_failing_it_does_not_fail_the_year(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->term(), 'Low Supw', ['SUPW' => 10]);

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame(['3.04', 'Second', true], [$r->value, $r->classLabel, $r->passed]);
        $this->assertSame(['FAIL', true, false], [$r->subject('SUPW')->bandLabel, $r->subject('SUPW')->isFail, $r->subject('SUPW')->counted]);
    }

    public function test_a_gpa_above_2_with_a_failed_compulsory_subject_is_fail(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->term(), 'Failed Myanmar', ['MYAN' => 30]);

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        // 70.01 - 6.99 = 63.02 / 23 = 2.74: a Second-class GPA, but Myanmar is F.
        $this->assertSame(['2.74', false, 'Fail'], [$r->value, $r->passed, $r->classLabel]);
        $this->assertSame(['F', true], [$r->subject('MYAN')->bandLabel, $r->subject('MYAN')->isFail]);
    }

    public function test_a_failed_elective_lowers_the_gpa_but_does_not_fail_the_year(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->term(), 'Failed Physics', ['PHYS' => 30]);

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        // 70.01 - 5.34 = 64.67 / 23 = 2.81.
        $this->assertSame(['2.81', true, 'Second'], [$r->value, $r->passed, $r->classLabel]);
    }

    // --- Boundaries (rules file section 11) ----------------------------------------------------

    public function test_subject_mark_boundaries_39_5_and_40(): void
    {
        $section = $this->levelSection('Grade 5-A', '5');
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'SOCST', 'COMP', 'PE', 'MUSIC', 'SUPW'];
        $marks = fn ($math) => array_merge(array_fill_keys($codes, 70), ['MATH' => $math]);

        $justPass = $this->pupil('Rounded Up', $section);
        $this->setMarks($justPass, $section, $this->term(), $marks('39.5'));
        $exactly = $this->pupil('Exactly Forty', $section);
        $this->setMarks($exactly, $section, $this->term(), $marks(40));
        $justFail = $this->pupil('Just Below', $section);
        $this->setMarks($justFail, $section, $this->term(), $marks('39.49'));

        $all = $this->results->sectionTerm($section, $this->term());

        $this->assertSame(['40', 'PASS', true], [$all->for($justPass)->subject('MATH')->roundedMark, $all->for($justPass)->subject('MATH')->bandLabel, $all->for($justPass)->passed]);
        $this->assertSame(['40', 'PASS', true], [$all->for($exactly)->subject('MATH')->roundedMark, $all->for($exactly)->subject('MATH')->bandLabel, $all->for($exactly)->passed]);
        // 39.49 rounds to 39: Math fails, so the year fails although the average (66.9 -> 67) is Second.
        $this->assertSame(['39', 'FAIL', false, '67', 'Fail'], [
            $all->for($justFail)->subject('MATH')->roundedMark, $all->for($justFail)->subject('MATH')->bandLabel,
            $all->for($justFail)->passed, $all->for($justFail)->value, $all->for($justFail)->classLabel,
        ]);
    }

    public function test_average_boundaries_60_5_and_94_5_and_the_rounding_mode(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE'];
        $second = $this->pupil('Sixty Point Five', $section);
        $this->setMarks($second, $section, $this->term(), array_combine($codes, [60, 61, 60, 61, 60, 61]));
        $distinction = $this->pupil('Ninety Four Point Five', $section);
        $this->setMarks($distinction, $section, $this->term(), array_combine($codes, [94, 95, 94, 95, 94, 95]));

        $all = $this->results->sectionTerm($section, $this->term());
        $this->assertSame(['60.5000', '61', 'Second'], [$all->for($second)->unrounded, $all->for($second)->value, $all->for($second)->classLabel]);
        $this->assertSame(['95', 'Distinction'], [$all->for($distinction)->value, $all->for($distinction)->classLabel]);

        // If the school answers Q10 with "round down", the Admin changes the scheme and 60.5 is Third.
        GradingScheme::where('code', 'KG_9')->update(['rounding_mode' => 'down']);
        $all = $this->results->sectionTerm($section, $this->term());
        $this->assertSame(['60', 'Third'], [$all->for($second)->value, $all->for($second)->classLabel]);
        $this->assertSame(['94', 'First'], [$all->for($distinction)->value, $all->for($distinction)->classLabel]);
    }

    public function test_gpa_boundaries_2_675_and_3_675(): void
    {
        $rules = $this->results->rules(GradingScheme::where('code', 'G11_12')->sole());

        $this->assertSame(['2.68', 'Second', false], array_values($this->results->classify($rules, '2.675')));
        $this->assertSame(['3.68', 'Distinction', false], array_values($this->results->classify($rules, '3.675')));
        $this->assertSame(['3.67', 'First', false], array_values($this->results->classify($rules, '3.6749')));
        $this->assertSame(['1.99', 'Fail', true], array_values($this->results->classify($rules, '1.994')));
        $this->assertSame(['2.00', 'Third', false], array_values($this->results->classify($rules, '1.995')));
    }

    // --- Blocked and incomplete ------------------------------------------------------------------

    public function test_grade_10_is_blocked_until_its_counted_subjects_and_q19_are_confirmed(): void
    {
        $section = $this->levelSection('Grade 10-A', '10');
        $student = $this->pupil('Ten Student', $section);
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'COMP', 'BIO', 'CHEM', 'PHYS', 'WHIST', 'WGEO', 'MUSIC', 'SUPW', 'PE'];
        $this->setMarks($student, $section, $this->term(), array_fill_keys($codes, 90));

        $r = $this->results->sectionTerm($section, $this->term())->for($student);
        $this->assertSame(['blocked', null, null, null], [$r->status, $r->value, $r->classLabel, $r->position]);
        $this->assertStringContainsString('Q6', implode(' ', $r->reasons));
        // Subject marks, letters and points still show.
        $this->assertSame(['90', 'A', '10.00'], [$r->subject('MATH')->roundedMark, $r->subject('MATH')->bandLabel, $r->subject('MATH')->bandValue]);

        // The school confirms seven counted subjects: still blocked by the Q19 defaults.
        $offerings = SubjectOffering::whereHas('gradeLevel', fn ($q) => $q->where('code', '10'))->get();
        foreach ($offerings as $o) {
            $o->update(['pending_question' => null, 'counts_in_result' => in_array($o->subject->code, ['MORAL', 'MYAN', 'ENG', 'MATH', 'BIO', 'CHEM', 'PHYS'], true)]);
        }
        $r = $this->results->sectionTerm($section, $this->term())->for($student);
        $this->assertSame('blocked', $r->status);
        $this->assertStringContainsString('Q19', implode(' ', $r->reasons));

        // Q19 answered too: seven A grades are 70 points, Distinction.
        $g10 = GradingScheme::where('code', 'G10')->sole();
        $g10->update(['pending_question' => 'Q10']);
        GradingSchemeBand::where('grading_scheme_id', $g10->id)->update(['pending_question' => null]);
        $r = $this->results->sectionTerm($section, $this->term())->for($student);
        $this->assertSame(['complete', 'point_total', 'Point total', '70', 'Distinction', true],
            [$r->status, $r->basis, $r->resultLabel, $r->value, $r->classLabel, $r->passed]);
    }

    public function test_no_result_until_every_counted_subject_has_a_mark(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil('Missing English', $section);
        $this->setMarks($student, $section, $this->term(), array_merge(self::KG_EXAMPLE, ['ENG' => null]));

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame(['incomplete', null, null, null, null], [$r->status, $r->value, $r->classLabel, $r->passed, $r->position]);
        $this->assertStringContainsString('ENGLISH', implode(' ', $r->reasons));
        $this->assertSame('89.00', $r->subject('MORAL')->mark); // the subject's own mark still shows
    }

    public function test_a_grade_12_student_without_an_elective_choice_has_no_result(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->pupil('No Choice Yet', $section);
        $this->setMarks($student, $section, $this->term(), self::G12_EXAMPLE);

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame('incomplete', $r->status);
        $this->assertStringContainsString('slot A', implode(' ', $r->reasons));
    }

    public function test_a_section_without_a_grade_level_has_no_result(): void
    {
        $section = $this->levelSection('Nursery', '1');
        $section->update(['grade_level_id' => null]);
        $student = $this->pupil('Nursery Child', $section);

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame('blocked', $r->status);
        $this->assertStringContainsString('no grade level', implode(' ', $r->reasons));
    }

    // --- Position --------------------------------------------------------------------------------

    public function test_tied_students_share_a_position_and_the_next_is_skipped(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE'];
        $a = $this->pupil('Ninety A', $section);
        $this->setMarks($a, $section, $this->term(), self::KG_EXAMPLE);
        // 89.5 average prints as 90, so it ties with 90 (ranked by the printed figure).
        $b = $this->pupil('Ninety B', $section);
        $this->setMarks($b, $section, $this->term(), array_combine($codes, [89, 90, 90, 89, 90, 89]));
        $c = $this->pupil('Eighty Five', $section);
        $this->setMarks($c, $section, $this->term(), array_fill_keys($codes, 85));
        $d = $this->pupil('Incomplete', $section);
        $this->setMarks($d, $section, $this->term(), ['MORAL' => 99]);
        $this->pupil('Withdrawn', $section, 'Withdrawn');

        $all = $this->results->sectionTerm($section, $this->term());

        $this->assertSame([1, 1, 3, null], [$all->for($a)->position, $all->for($b)->position, $all->for($c)->position, $all->for($d)->position]);
        $this->assertSame(3, $all->for($a)->rankedCount);
        $this->assertNull($all->for(\App\Models\Student::where('name', 'Withdrawn')->sole()));
    }

    // --- Q20: where failed students are ranked ---------------------------------------------------

    /** Passing 70, passing 60, and a higher average (86) that fails on Mathematics. */
    private function rankingSection(): array
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'MAPE'];
        $seventy = $this->pupil('Passes 70', $section);
        $this->setMarks($seventy, $section, $this->term(), array_fill_keys($codes, 70));
        $sixty = $this->pupil('Passes 60', $section);
        $this->setMarks($sixty, $section, $this->term(), array_fill_keys($codes, 60));
        $failed = $this->pupil('Fails Maths', $section);
        $this->setMarks($failed, $section, $this->term(), array_merge(array_fill_keys($codes, 99), ['MATH' => 20]));
        $failedToo = $this->pupil('Fails Maths Too', $section);
        $this->setMarks($failedToo, $section, $this->term(), array_merge(array_fill_keys($codes, 99), ['MATH' => 20]));

        return [$section, $seventy, $sixty, $failed, $failedToo];
    }

    public function test_by_default_failed_students_are_ranked_together_with_passing_ones(): void
    {
        [$section, $seventy, $sixty, $failed, $failedToo] = $this->rankingSection();

        $all = $this->results->sectionTerm($section, $this->term());

        $this->assertSame(['86', false], [$all->for($failed)->value, $all->for($failed)->passed]);
        $this->assertSame([1, 1, 3, 4], [$all->for($failed)->position, $all->for($failedToo)->position, $all->for($seventy)->position, $all->for($sixty)->position]);
    }

    public function test_with_failed_last_every_failed_student_comes_after_all_who_passed(): void
    {
        [$section, $seventy, $sixty, $failed, $failedToo] = $this->rankingSection();
        GradingScheme::where('code', 'KG_9')->update(['failed_ranking' => 'failed_last']);

        $all = $this->results->sectionTerm($section, $this->term());

        // Passing students first; the two failed students tie for the next place.
        $this->assertSame([1, 2, 3, 3], [$all->for($seventy)->position, $all->for($sixty)->position, $all->for($failed)->position, $all->for($failedToo)->position]);
        $this->assertSame(4, $all->for($seventy)->rankedCount);
    }

    // --- The year and the promotion recommendation -----------------------------------------------

    public function test_a_subjects_year_mark_is_the_average_of_its_four_rounded_term_marks(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil('Four Terms', $section);
        foreach ([80, 81, 82, '85.5'] as $i => $math) {
            $this->setMarks($student, $section, $this->term($i + 1), array_merge(self::KG_EXAMPLE, ['MATH' => $math]));
        }

        $r = $this->results->sectionYear($section)->for($student);

        // (80 + 81 + 82 + 86) / 4 = 82.25, which prints as 82.
        $this->assertSame(['82.25', '82'], [$r->subject('MATH')->mark, $r->subject('MATH')->roundedMark]);
        $this->assertSame(['complete', '90', 'First', 'Promote'], [$r->status, $r->value, $r->classLabel, $r->recommendation]);
    }

    public function test_a_year_with_a_term_still_unmarked_is_incomplete(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil('Three Terms', $section);
        foreach ([1, 2, 3] as $sequence) {
            $this->setMarks($student, $section, $this->term($sequence), self::KG_EXAMPLE);
        }

        $r = $this->results->sectionYear($section)->for($student);

        $this->assertSame(['incomplete', null], [$r->status, $r->recommendation]);
    }

    public function test_recommendations_follow_section_9(): void
    {
        $kg = $this->levelSection('Grade 2-A', '2');
        $pass = $this->pupil('Promoted Two', $kg);
        $fail = $this->pupil('Retained Two', $kg);
        $eleven = $this->levelSection('Grade 11-A', '11');
        $eleventh = $this->pupil('Promoted Eleven', $eleven);
        $twelve = $this->levelSection('Grade 12-A', '12');
        $ten = $this->levelSection('Grade 10-A', '10');
        $tenth = $this->pupil('Tenth Grader', $ten);

        foreach ($this->terms as $term) {
            $this->setMarks($pass, $kg, $term, self::KG_EXAMPLE);
            $this->setMarks($fail, $kg, $term, array_merge(self::KG_EXAMPLE, ['MATH' => 20]));
            $this->setMarks($eleventh, $eleven, $term, self::G12_EXAMPLE);
        }
        foreach (['A' => 'BIO', 'B' => 'CHEM', 'C' => 'PHYS'] as $slot => $code) {
            $this->choose($eleventh, '11', $slot, $code);
        }
        $graduate = $this->grade12Example($twelve, $this->term(1), 'Graduating Twelve');
        foreach (array_slice($this->terms, 1) as $term) {
            $this->setMarks($graduate, $twelve, $term, self::G12_EXAMPLE);
        }
        $failing12 = $this->grade12Example($twelve, $this->term(1), 'Retained Twelve', ['MATH' => 10]);
        foreach (array_slice($this->terms, 1) as $term) {
            $this->setMarks($failing12, $twelve, $term, array_merge(self::G12_EXAMPLE, ['MATH' => 10]));
        }

        $this->assertSame('Promote', $this->results->sectionYear($kg)->for($pass)->recommendation);
        $this->assertSame('Retain', $this->results->sectionYear($kg)->for($fail)->recommendation);
        $this->assertSame('Promote', $this->results->sectionYear($eleven)->for($eleventh)->recommendation);
        $this->assertSame('Graduate', $this->results->sectionYear($twelve)->for($graduate)->recommendation);
        $this->assertSame('Retain', $this->results->sectionYear($twelve)->for($failing12)->recommendation);
        // Grade 10: no automatic recommendation (Q11), whatever the marks.
        $this->assertNull($this->results->sectionYear($ten)->for($tenth)->recommendation);
    }

    public function test_a_subject_taught_outside_the_curriculum_shows_its_mark_but_no_letter(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil('Extra Subject', $section);
        $this->setMarks($student, $section, $this->term(), array_merge(self::KG_EXAMPLE, ['ACCT' => 77]));

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame(['77.00', null, false, false], [$r->subject('ACCT')->mark, $r->subject('ACCT')->bandLabel, $r->subject('ACCT')->inCurriculum, $r->subject('ACCT')->counted]);
        $this->assertSame('90', $r->value);
    }

    public function test_results_never_use_the_department_grade_scale(): void
    {
        $source = file_get_contents(app_path('Services/ResultService.php'));
        $this->assertStringNotContainsString('GradeScaleBand', $source);
    }
}
