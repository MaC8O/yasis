<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\ReportRule;
use App\Models\ResultFreeze;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Services\AttendanceScoreService;
use App\Services\ReportCardActionsService;
use App\Services\ResultFreezeService;
use App\Services\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * Phase 9b, the school's attendance answers (Q8, Q16): an excused absence counts as an absence
 * (FQ1), three late arrivals are one absence, the 80% rule is checked over the school year, and
 * more than 10% absence in a term is flagged (per subject, from the daily attendance: FQ2). Each is
 * a report rule; frozen terms keep the rules they were frozen with.
 */
class AttendanceRulesTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $principal;

    private StaffProfile $homeroom;

    private Section $section;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms();
        $this->principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        $this->homeroom = $this->makeStaff('teacher', 'Teacher', 'homeroom@test.local');
        $this->section = $this->levelSection('Grade 6-A', '6');
        $this->section->update(['homeroom_teacher_id' => $this->homeroom->id]);
        $this->student = $this->pupil('Invented Six', $this->section);
    }

    /** @param  list<string>  $statuses  one record a day from the term's first day */
    private function attend(array $statuses, int $term = 1, ?Student $student = null): void
    {
        foreach ($statuses as $i => $status) {
            AttendanceRecord::create([
                'student_id' => ($student ?? $this->student)->id, 'section_id' => $this->section->id, 'term_id' => $this->terms[$term - 1]->id,
                'attendance_date' => $this->terms[$term - 1]->start_date->copy()->addDays($i)->toDateString(),
                'status' => $status, 'recorded_by' => $this->homeroom->id,
            ]);
        }
    }

    private function row(int $term = 1): array
    {
        return app(AttendanceScoreService::class)->forSectionTerm($this->section, $this->terms[$term - 1])['students'][$this->student->id];
    }

    private function rule(string $key, string $value): void
    {
        ReportRule::where('key', $key)->update(['value' => $value]);
    }

    public function test_one_excused_rule_covers_the_mark_days_absent_and_the_minimum(): void
    {
        $this->attend(['Present', 'Present', 'Present', 'Present', 'Excused']);

        // Counted as an absence (the default, FQ1): 4 of 5 days, 80%, one day absent.
        $this->assertSame([4, 5, 1, '80.0000000000'], [$this->row()['attended'], $this->row()['counted'], $this->row()['days_absent'], $this->row()['pct']]);

        // Left out: 4 of 4 days, 100%, no day absent: the mark, the card and the check all agree.
        $this->rule('excused_absences', 'leave_out');
        $this->assertSame([4, 4, 0, '100.0000000000'], [$this->row()['attended'], $this->row()['counted'], $this->row()['days_absent'], $this->row()['pct']]);
    }

    public function test_late_arrivals_count_three_to_an_absence_in_whole_multiples_only(): void
    {
        $lates = fn (int $n) => array_merge(array_fill(0, $n, 'Tardy'), array_fill(0, 10 - $n, 'Present'));
        $cases = [2 => [0, 10], 3 => [1, 9], 5 => [1, 9], 6 => [2, 8]];
        foreach ($cases as $n => [$absences, $attended]) {
            AttendanceRecord::query()->delete();
            $this->attend($lates($n));
            $this->assertSame([$absences, $attended, 10, $absences], [$this->row()['late_absences'], $this->row()['attended'], $this->row()['counted'], $this->row()['days_absent']], "{$n} lates");
        }
        $this->assertSame('8 of 10 days · 6 late = 2 absences', AttendanceScoreService::describe($this->row()));

        $this->rule('lates_per_absence', '4');
        $this->assertSame(1, $this->row()['late_absences']);
        $this->rule('lates_per_absence', '0');
        $this->assertSame([0, 10], [$this->row()['late_absences'], $this->row()['attended']]);
    }

    public function test_the_minimum_is_checked_over_the_school_year_with_lates_converted_on_the_years_total(): void
    {
        // Two lates in each of three terms: none counts in a term, but six in the year are two absences.
        foreach ([1, 2, 3] as $term) {
            $this->attend(['Tardy', 'Tardy', 'Present', 'Present', 'Absent'], $term);
        }
        $this->assertSame([0, 0, 0], [$this->row(1)['late_absences'], $this->row(2)['late_absences'], $this->row(3)['late_absences']]);
        $actions = app(ReportCardActionsService::class);

        // Each term alone: 4 of 5 = 80%, not below. The year to Term 3: (12 - 2) of 15 = 66.7%, below.
        $below = $actions->belowMinimumAttendance($this->terms[2]);
        $this->assertSame(['66.7', 2], [$below[0]['pct'], $below[0]['late_absences']]);
        // Up to Term 1 only, the year so far is just Term 1.
        $this->assertSame([], $actions->belowMinimumAttendance($this->terms[0]));

        $this->rule('attendance_check_period', 'term');
        $this->assertSame([], $actions->belowMinimumAttendance($this->terms[2]));
    }

    public function test_more_than_ten_percent_absent_in_a_term_is_flagged_and_exactly_ten_is_not(): void
    {
        $ten = $this->pupil('Invented Exactly Ten', $this->section);
        $this->attend(array_merge(array_fill(0, 9, 'Present'), ['Absent']), 1, $ten);           // 10% absent
        $this->attend(array_merge(array_fill(0, 8, 'Present'), ['Absent', 'Excused']), 1);         // 20% absent

        $flagged = app(ReportCardActionsService::class)->aboveSubjectAbsence($this->terms[0]);
        $this->assertSame([['Invented Six', '20.0']], array_map(fn ($r) => [$r['student'], $r['absent_pct']], $flagged));

        $this->rule('max_subject_absence_pct', '25');
        $this->assertSame([], app(ReportCardActionsService::class)->aboveSubjectAbsence($this->terms[0]));
    }

    public function test_a_frozen_term_keeps_the_attendance_rules_it_was_frozen_with(): void
    {
        $this->setMarks($this->student, $this->section, $this->terms[0], array_fill_keys(['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'SOCST', 'COMP', 'PE', 'MUSIC'], 80));
        $this->attend(['Present', 'Present', 'Present', 'Excused']);
        $freezes = app(ResultFreezeService::class);
        $freeze = $freezes->lock($this->terms[0], $this->principal->user);

        $this->assertSame(['excused' => 'count_as_absent', 'lates_per_absence' => 3, 'excused_in_days_absent' => true], $freeze->fresh()->attendance_rules);
        $stored = fn () => app(ResultService::class)->sectionTerm($this->section, $this->terms[0])->students->first();
        $this->assertSame(1, \App\Models\StudentResult::where('result_freeze_id', $freeze->id)->value('days_absent'));

        // The school changes its mind; an applied grade change re-freezes with the frozen rules.
        $this->rule('excused_absences', 'leave_out');
        $freezes->refreezeSection($this->section, $this->terms[0], $this->principal->user, 'Grade change #1 applied');
        $active = ResultFreeze::active()->where('term_id', $this->terms[0]->id)->sole();
        $this->assertSame(1, \App\Models\StudentResult::where('result_freeze_id', $active->id)->current()->value('days_absent'));
        $this->assertSame('complete', $stored()->status);

        // Recalculating with the current rules picks the change up.
        $freezes->unlock($this->terms[0], $this->principal->user);
        $relocked = $freezes->lock($this->terms[0], $this->principal->user, ResultFreeze::RULES_CURRENT, 'The school answered FQ1');
        $this->assertSame('leave_out', $relocked->fresh()->attendance_rules['excused']);
        $this->assertSame(0, \App\Models\StudentResult::where('result_freeze_id', $relocked->id)->value('days_absent'));
    }

    public function test_the_admin_edits_the_attendance_rules_and_the_migration_merges_the_old_days_absent_rule(): void
    {
        $admin = $this->makeStaff('admin', 'Admin', 'admin@test.local');
        $values = ReportRule::pluck('value', 'key')->all();
        $this->actingAs($admin->user)->put('/admin/report-rules', ['values' => ['lates_per_absence' => '11'] + $values])->assertSessionHasErrors('values.lates_per_absence');
        $this->actingAs($admin->user)->put('/admin/report-rules', ['values' => ['lates_per_absence' => '0', 'excused_absences' => 'leave_out'] + $values])->assertSessionHas('status');
        $this->assertSame(['0', 'leave_out'], [ReportRule::value('lates_per_absence'), ReportRule::value('excused_absences')]);
        $this->actingAs($this->principal->user)->get('/principal/report-rules')->assertOk()
            ->assertSee('Late arrivals that count as one absence')->assertSee('Needs confirmation from the school (FQ1)')->assertSee('(FQ2)');

        // Rolling the rules migration back gives the old setting its matching value, and forward again.
        $migration = require database_path('migrations/2026_12_26_090200_apply_school_answers_to_attendance_rules.php');
        $migration->down();
        $this->assertSame(['absent_only', 'Q8', null], [ReportRule::value('days_absent_counts'), ReportRule::where('key', 'days_absent_counts')->value('pending_question'), ReportRule::where('key', 'lates_per_absence')->value('value')]);
        $migration->up();
        $this->assertSame(['leave_out', 'FQ1', '3'], [ReportRule::value('excused_absences'), ReportRule::where('key', 'excused_absences')->value('pending_question'), ReportRule::value('lates_per_absence')]);
    }
}
