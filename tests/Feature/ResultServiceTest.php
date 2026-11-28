<?php

namespace Tests\Feature;

use App\Models\GradingScheme;
use App\Models\Section;
use App\Models\Student;
use App\Services\ResultService;
use Brick\Math\BigDecimal;
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

    public function test_the_six_subject_kg_worked_example_still_averages_90_first(): void
    {
        // The sample statement's six marks (section 4.2), as an arithmetic check of the average and its class.
        $rules = $this->results->rules(GradingScheme::where('code', 'KG_9')->sole());
        $average = (string) BigDecimal::of(89 + 90 + 91 + 82 + 94 + 94)->dividedBy(6, 10);

        $this->assertSame(['90', 'First', false], array_slice(array_values($this->results->classify($rules, $average)), 0, 3));
    }

    public function test_kg_four_averages_seven_subjects_with_character_building_and_not_social_study(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $student = $this->pupil('Sample Two', $section);
        $this->setMarks($student, $section, $this->term(), array_merge(self::KG_EXAMPLE, ['CHAR' => 83]));
        // Social Study is on the card but not counted (Q4): no mark needed.

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        // (89 + 90 + 91 + 82 + 94 + 94 + 83) / 7 = 89.
        $this->assertSame(['complete', 'average', 'Average', '89', 'First', true],
            [$r->status, $r->basis, $r->resultLabel, $r->value, $r->classLabel, $r->passed]);
        $this->assertSame(['89.00', '89', 'PASS', false, true], [
            $r->subject('MORAL')->mark, $r->subject('MORAL')->roundedMark, $r->subject('MORAL')->bandLabel,
            $r->subject('MORAL')->isFail, $r->subject('MORAL')->counted,
        ]);
        $this->assertSame([true, '83'], [$r->subject('CHAR')->counted, $r->subject('CHAR')->roundedMark]);
        $this->assertFalse($r->subject('SOCST')->counted);
        $this->assertNull($r->subject('SOCST')->mark);

        // Without a Character Building mark there is no result yet.
        $this->setMark($student, $section, 'CHAR', $this->term(), null);
        $r = $this->results->sectionTerm($section, $this->term())->for($student);
        $this->assertSame('incomplete', $r->status);
        $this->assertStringContainsString('CHARACTER BUILDING', implode(' ', $r->reasons));
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
        // The other track's subjects are listed but not taken.
        $this->assertFalse($r->subject('WHIST')->taken);
        $this->assertTrue($r->subject('BIO')->taken);
        $this->assertSame('science', $r->subject('BIO')->track);
    }

    public function test_an_arts_track_student_takes_world_history_geography_and_accounting(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        // The science marks moved to the arts subjects: the same letters, so the same GPA.
        $student = $this->grade12Example($section, $this->term(), 'Arts Taker', ['BIO' => null, 'CHEM' => null, 'PHYS' => null, 'WHIST' => 76, 'WGEO' => 72, 'ACCT' => 71]);
        $this->setTrack($student, 'arts');

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame(['complete', '3.0439', '3.04'], [$r->status, $r->unrounded, $r->value]);
        foreach (['WHIST', 'WGEO', 'ACCT'] as $code) {
            $this->assertTrue($r->subject($code)->taken && $r->subject($code)->counted, $code);
        }
        foreach (['BIO', 'CHEM', 'PHYS'] as $code) {
            $this->assertFalse($r->subject($code)->taken, $code);
        }

        // A science mark doesn't make an arts student's result.
        $this->setMarks($student, $section, $this->term(), ['BIO' => 100, 'ACCT' => null]);
        $r = $this->results->sectionTerm($section, $this->term())->for($student);
        $this->assertSame('incomplete', $r->status);
        $this->assertStringContainsString('ACCOUNTING', implode(' ', $r->reasons));
    }

    public function test_supw_has_a_word_not_a_mark_and_counts_in_nothing(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->grade12Example($section, $this->term(), 'Supw Incomplete');
        $this->setWord($student, $section, $this->term(), 'Incomplete');

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame(['3.04', 'Second', true], [$r->value, $r->classLabel, $r->passed]);
        $this->assertSame(['INCOMPLETE', false, false, null, 'completion'],
            [$r->subject('SUPW')->bandLabel, $r->subject('SUPW')->isFail, $r->subject('SUPW')->counted, $r->subject('SUPW')->mark, $r->subject('SUPW')->gradingMode]);

        // Grades 5-9: SUPW is out of the average too, which is now nine subjects.
        $five = $this->levelSection('Grade 5-A', '5');
        $pupil = $this->pupil('Five Supw', $five);
        $nine = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'SOCST', 'COMP', 'PE', 'MUSIC'];
        $this->setMarks($pupil, $five, $this->term(), array_merge(array_fill_keys($nine, 70), ['MATH' => 79, 'SUPW' => 5]));
        $this->setWord($pupil, $five, $this->term(), 'Complete');
        $r = $this->results->sectionTerm($five, $this->term())->for($pupil);
        // (8 x 70 + 79) / 9 = 71; the SUPW item mark is ignored.
        $this->assertSame(['complete', '71', 'COMPLETE', false], [$r->status, $r->value, $r->subject('SUPW')->bandLabel, $r->subject('SUPW')->counted]);
        $this->assertSame(9, collect($r->subjects)->where('counted', true)->count());
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
        $codes = ['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'SOCST', 'COMP', 'PE', 'MUSIC'];
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
        // 39.49 rounds to 39: Math fails, so the year fails although the average (599 / 9 = 66.6 -> 67) is Second.
        $this->assertSame(['39', 'FAIL', false, '67', 'Fail'], [
            $all->for($justFail)->subject('MATH')->roundedMark, $all->for($justFail)->subject('MATH')->bandLabel,
            $all->for($justFail)->passed, $all->for($justFail)->value, $all->for($justFail)->classLabel,
        ]);
    }

    public function test_average_boundaries_60_5_and_94_5_and_the_rounding_mode(): void
    {
        // Seven whole marks can't average exactly x.5, so the boundaries are checked on the
        // classification itself, and on a section average just above 60.5.
        $rules = fn () => $this->results->rules(GradingScheme::where('code', 'KG_9')->sole());
        $this->assertSame(['61', 'Second'], array_slice(array_values($this->results->classify($rules(), '60.5')), 0, 2));
        $this->assertSame(['95', 'Distinction'], array_slice(array_values($this->results->classify($rules(), '94.5')), 0, 2));

        $section = $this->levelSection('Grade 2-A', '2');
        $above = $this->pupil('Sixty Point Five Seven', $section);
        $this->setMarks($above, $section, $this->term(), array_combine(self::KG_CODES, [60, 61, 60, 61, 60, 61, 61])); // 424 / 7
        $all = $this->results->sectionTerm($section, $this->term());
        $this->assertSame(['60.5714', '61', 'Second'], [$all->for($above)->unrounded, $all->for($above)->value, $all->for($above)->classLabel]);

        // If the school answers Q10 with "round down", the Admin changes the scheme and 60.5 is Third.
        GradingScheme::where('code', 'KG_9')->update(['rounding_mode' => 'down']);
        $this->assertSame(['60', 'Third'], array_slice(array_values($this->results->classify($rules(), '60.5')), 0, 2));
        $this->assertSame(['94', 'First'], array_slice(array_values($this->results->classify($rules(), '94.5')), 0, 2));
        $all = $this->results->sectionTerm($section, $this->term());
        $this->assertSame(['60', 'Third'], [$all->for($above)->value, $all->for($above)->classLabel]);
    }

    public function test_gpa_boundaries_2_675_and_3_675(): void
    {
        $rules = $this->results->rules(GradingScheme::where('code', 'G11_12')->sole());

        $classify = fn (string $v) => array_slice(array_values($this->results->classify($rules, $v)), 0, 3);
        $this->assertSame(['2.68', 'Second', false], $classify('2.675'));
        $this->assertSame(['3.68', 'Distinction', false], $classify('3.675'));
        $this->assertSame(['3.67', 'First', false], $classify('3.6749'));
        $this->assertSame(['1.99', 'Fail', true], $classify('1.994'));
        $this->assertSame(['2.00', 'Third', false], $classify('1.995'));
    }

    // --- Blocked and incomplete ------------------------------------------------------------------

    /** A Grade 10 student on the science track with the given marks for the seven counted subjects. */
    private function tenth(Section $section, string $name, array $marks): Student
    {
        $student = $this->pupil($name, $section);
        $this->setTrack($student, 'science');
        $seven = ['MORAL', 'MYAN', 'ENG', 'MATH', 'BIO', 'CHEM', 'PHYS'];
        // Computer, PE and the arts subjects get marks too: none of them counts.
        $this->setMarks($student, $section, $this->term(), array_merge(['COMP' => 20, 'PE' => 20, 'WHIST' => 99, 'WGEO' => 99, 'MUSIC' => 99], array_combine($seven, $marks)));

        return $student;
    }

    public function test_grade_10_counts_seven_subjects_and_shows_the_total_and_class_when_nothing_waits_on_q19(): void
    {
        $section = $this->levelSection('Grade 10-A', '10');
        $student = $this->tenth($section, 'Ten Distinction', [90, 90, 90, 90, 90, 90, 90]);

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        // Seven A grades: 70 points, Distinction; the G.P.A row's average is 90.
        $this->assertSame(['complete', 'point_total', '70', 'Distinction', true, '90', null],
            [$r->status, $r->basis, $r->value, $r->classLabel, $r->passed, $r->average, $r->classPending]);
        $this->assertSame(7, collect($r->subjects)->where('counted', true)->count());
        $this->assertFalse($r->subject('COMP')->counted); // FQ4: not counted by default
        $this->assertSame('FQ4', $r->subject('COMP')->pendingQuestion);
        $this->assertFalse($r->subject('PE')->counted);
        $this->assertFalse($r->subject('MUSIC')->taken); // Music is on the arts track
    }

    public function test_grade_10_cases_q19_covers_show_the_total_with_the_class_pending(): void
    {
        $section = $this->levelSection('Grade 10-A', '10');
        // A counted subject below 40: the Fail band, flagged Q19.
        $low = $this->tenth($section, 'Ten Low Subject', [90, 90, 90, 90, 90, 90, 30]);
        // Seven D grades (40-48, 5 points each): 35 points, in the Q19-flagged band below 40.
        $total35 = $this->tenth($section, 'Ten Thirty Five', [45, 45, 45, 45, 45, 45, 45]);
        // Four C (7) and three D (5): 43 points, Third, nothing unclear.
        $third = $this->tenth($section, 'Ten Third', [60, 60, 60, 60, 45, 45, 45]);

        $all = $this->results->sectionTerm($section, $this->term());

        $this->assertSame(['complete', '60', null, null, 'Q19'],
            [$all->for($low)->status, $all->for($low)->value, $all->for($low)->classLabel, $all->for($low)->passed, $all->for($low)->classPending]);
        $this->assertSame('Point total 60 · class pending school answer (Q19)', $all->for($low)->summary());
        $this->assertSame(['35', null, 'Q19'], [$all->for($total35)->value, $all->for($total35)->classLabel, $all->for($total35)->classPending]);
        $this->assertSame(['43', 'Third', true, null], [$all->for($third)->value, $all->for($third)->classLabel, $all->for($third)->passed, $all->for($third)->classPending]);

        // Position by the average (Q12): 81 (Low Subject), 54 (Third), 45 (Thirty Five).
        $this->assertSame(['81', '54', '45'], [$all->for($low)->average, $all->for($third)->average, $all->for($total35)->average]);
        $this->assertSame([1, 2, 3], [$all->for($low)->position, $all->for($third)->position, $all->for($total35)->position]);
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

    public function test_a_grade_12_student_without_a_track_has_no_result(): void
    {
        $section = $this->levelSection('Grade 12-A', '12');
        $student = $this->pupil('No Track Yet', $section);
        $this->setMarks($student, $section, $this->term(), self::G12_EXAMPLE);

        $r = $this->results->sectionTerm($section, $this->term())->for($student);

        $this->assertSame('incomplete', $r->status);
        $this->assertStringContainsString('No track chosen', implode(' ', $r->reasons));
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

    public function test_tied_students_share_a_position_and_the_next_follows_on(): void
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $codes = self::KG_CODES;
        $a = $this->pupil('Ninety A', $section);
        $this->setMarks($a, $section, $this->term(), self::KG_EXAMPLE);
        // 89.57 average prints as 90, so it ties with 90 (ranked by the printed figure).
        $b = $this->pupil('Ninety B', $section);
        $this->setMarks($b, $section, $this->term(), array_combine($codes, [89, 90, 90, 89, 90, 89, 90]));
        $c = $this->pupil('Eighty Five', $section);
        $this->setMarks($c, $section, $this->term(), array_fill_keys($codes, 85));
        $d = $this->pupil('Incomplete', $section);
        $this->setMarks($d, $section, $this->term(), ['MORAL' => 99]);
        $this->pupil('Withdrawn', $section, 'Withdrawn');

        $all = $this->results->sectionTerm($section, $this->term());

        // Dense (Q13): 1, 1, 2, not 1, 1, 3.
        $this->assertSame([1, 1, 2, null], [$all->for($a)->position, $all->for($b)->position, $all->for($c)->position, $all->for($d)->position]);
        $this->assertSame(3, $all->for($a)->rankedCount);

        // A scheme set back to standard ties skips the next position, as freezes made before did.
        GradingScheme::where('code', 'KG_9')->update(['tie_ranking' => 'standard']);
        $all = $this->results->sectionTerm($section, $this->term());
        $this->assertSame([1, 1, 3], [$all->for($a)->position, $all->for($b)->position, $all->for($c)->position]);
        $this->assertNull($all->for(\App\Models\Student::where('name', 'Withdrawn')->sole()));
    }

    // --- Q20: where failed students are ranked ---------------------------------------------------

    /** Passing 70, passing 60, and a higher average (88) that fails on Mathematics. */
    private function rankingSection(): array
    {
        $section = $this->levelSection('Grade 2-A', '2');
        $codes = self::KG_CODES;
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

        $this->assertSame(['88', false], [$all->for($failed)->value, $all->for($failed)->passed]);
        $this->assertSame([1, 1, 2, 3], [$all->for($failed)->position, $all->for($failedToo)->position, $all->for($seventy)->position, $all->for($sixty)->position]);
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
        $this->setTrack($eleventh, 'science');
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
