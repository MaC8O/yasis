<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CalendarEvent;
use App\Models\ReportRule;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StudentResult;
use App\Services\ReportCardActionsService;
use App\Services\ResultFreezeService;
use App\Services\SchoolDays;
use Database\Seeders\K12StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\BuildsResults;
use Tests\Feature\Concerns\SeedsCoreData;
use Tests\TestCase;

/**
 * The attendance flags measured against the whole period (§8): a flag fires once the breach is
 * certain for the whole term or year, with school days from the term dates, the school week and
 * the Academic Calendar's holidays and term breaks. The `flag_basis` rule can switch back to the
 * days so far. A locked term's homeroom list shows its frozen figures. Invented names only.
 */
class AttendanceFlagsTest extends TestCase
{
    use BuildsResults, RefreshDatabase, SeedsCoreData;

    private StaffProfile $homeroom;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedYearWithTerms(); // four terms of nine weeks: 45 weekdays each
        $this->homeroom = $this->makeStaff('teacher', 'Teacher', 'homeroom@test.local');
        $this->section = $this->levelSection('Grade 6-A', '6');
        $this->section->update(['homeroom_teacher_id' => $this->homeroom->id]);
    }

    /** One record a day from the term's first day: $absent absences, then $present presents. */
    private function attend(Student $student, int $absent, int $present, int $term = 1): void
    {
        foreach (array_merge(array_fill(0, $absent, 'Absent'), array_fill(0, $present, 'Present')) as $i => $status) {
            AttendanceRecord::create([
                'student_id' => $student->id, 'section_id' => $this->section->id, 'term_id' => $this->terms[$term - 1]->id,
                'attendance_date' => $this->terms[$term - 1]->start_date->copy()->addDays($i)->toDateString(),
                'status' => $status, 'recorded_by' => $this->homeroom->id,
            ]);
        }
    }

    private function holiday(string $type, Carbon $from, ?Carbon $to = null, string $status = CalendarEvent::STATUS_PUBLISHED): void
    {
        CalendarEvent::create(['academic_year_id' => $this->year->id, 'title' => "Invented {$type}", 'event_type' => $type,
            'status' => $status, 'start_date' => $from->toDateString(), 'end_date' => $to?->toDateString()]);
    }

    private function names(array $flags): array
    {
        return array_map(fn ($r) => $r['student'], $flags);
    }

    public function test_school_days_follow_the_school_week_and_leave_out_holidays_and_term_breaks(): void
    {
        $days = app(SchoolDays::class);
        $term = $this->terms[0];
        $this->assertSame(45, $days->inTerm($term));
        $this->assertSame(180, $days->inYear($this->year->id));

        // A one-day holiday on a weekday, a term break over a full week (5 school days), and events
        // that aren't closures (an exam, a pending holiday) or fall on a weekend change nothing else.
        $monday = $term->start_date->copy()->next(Carbon::MONDAY);
        $this->holiday('Holiday', $monday);
        $this->holiday('Term Break', $monday->copy()->addWeek(), $monday->copy()->addWeek()->addDays(6));
        $this->holiday('Exam', $monday->copy()->addWeeks(2), $monday->copy()->addWeeks(2)->addDays(4));
        $this->holiday('Holiday', $monday->copy()->addWeeks(3), null, CalendarEvent::STATUS_PENDING);
        $this->holiday('Holiday', $monday->copy()->addWeeks(4)->next(Carbon::SATURDAY));
        $this->assertSame(39, $days->inTerm($term));

        ReportRule::where('key', 'school_week')->update(['value' => 'mon_sat']);
        // 9 Saturdays more (54); closed: the holiday, the break's Monday to Saturday, and the Saturday holiday now.
        $this->assertSame(54 - 1 - 6 - 1, $days->inTerm($term));
        ReportRule::where('key', 'school_week')->update(['value' => 'sun_thu']);
        $this->assertSame(45 - 1 - 5, $days->inTerm($term)); // the break's Sunday to Thursday
    }

    public function test_the_term_flag_fires_once_absences_are_over_ten_percent_of_the_whole_term(): void
    {
        // 45 school days: more than 4.5 absences breaks the limit, so 4 are allowed.
        $four = $this->pupil('Invented Four', $this->section);
        $five = $this->pupil('Invented Five', $this->section);
        $this->attend($four, 4, 1);  // 80% absent so far, but the term can't be lost yet
        $this->attend($five, 5, 20);
        $actions = app(ReportCardActionsService::class);

        $flagged = $actions->aboveSubjectAbsence($this->terms[0]);
        $this->assertSame(['Invented Five'], $this->names($flagged));
        $this->assertSame([5, 45, 4, '80.0'], [$flagged[0]['absences'], $flagged[0]['period_days'], $flagged[0]['limit'], $flagged[0]['pct']]);

        // The school can switch back to the days so far.
        ReportRule::where('key', 'flag_basis')->update(['value' => 'so_far']);
        $this->assertSame(['Invented Four', 'Invented Five'], $this->names($actions->aboveSubjectAbsence($this->terms[0])));
    }

    public function test_the_year_flag_fires_once_absences_are_over_twenty_percent_of_the_whole_year(): void
    {
        // 180 school days: more than 36 absences means 80% can't be reached.
        $edge = $this->pupil('Invented Thirty Six', $this->section);
        $over = $this->pupil('Invented Thirty Seven', $this->section);
        $this->attend($edge, 36, 9);
        $this->attend($over, 37, 8);
        $actions = app(ReportCardActionsService::class);

        $flagged = $actions->belowMinimumAttendance($this->terms[0]);
        $this->assertSame(['Invented Thirty Seven'], $this->names($flagged));
        $this->assertSame([37, 180, 36], [$flagged[0]['absences'], $flagged[0]['period_days'], $flagged[0]['limit']]);

        $page = $this->actingAs($this->makeStaff('vp_academic', 'VP_Academic', 'vp@test.local')->user)
            ->get("/report-cards/attendance?term={$this->terms[0]->id}")->assertOk();
        $page->assertSee('37 absences of 180 school days in the year (limit 36)')->assertSee('17.8% attended so far');
    }

    public function test_a_holiday_added_to_an_open_term_lowers_its_school_days_and_a_locked_term_keeps_its_frozen_figures(): void
    {
        $pupil = $this->pupil('Invented Holiday', $this->section);
        $this->setMarks($pupil, $this->section, $this->terms[0], array_fill_keys(['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'SOCST', 'COMP', 'PE', 'MUSIC'], 70));
        $this->attend($pupil, 3, 7);
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        $freeze = app(ResultFreezeService::class)->lock($this->terms[0], $principal->user);
        $frozen = StudentResult::where('result_freeze_id', $freeze->id)->sole();
        $days = app(SchoolDays::class);
        $this->assertSame([45, 45], [$days->inTerm($this->terms[0]), $days->inTerm($this->terms[1])]);

        // The school adds a holiday week inside both terms.
        foreach ([0, 1] as $t) {
            $monday = $this->terms[$t]->start_date->copy()->next(Carbon::MONDAY)->addWeek();
            $this->holiday('Holiday', $monday, $monday->copy()->addDays(4));
        }

        // The open term's school days go down (so its limits do too); the locked term's frozen figures stay.
        $this->assertSame(40, $days->inTerm($this->terms[1]));
        $this->assertSame([3, '70.00'], [$frozen->fresh()->days_absent, (string) $frozen->fresh()->attendance_pct]);
        $this->actingAs($this->homeroom->user)->get("/report-cards?section={$this->section->id}&term={$this->terms[0]->id}")->assertOk()
            ->assertSeeInOrder(['Invented Holiday', '70%', '3']);
    }

    public function test_a_locked_terms_homeroom_list_reads_the_frozen_figures_as_the_card_does(): void
    {
        $pupil = $this->pupil('Invented Locked', $this->section);
        $this->setMarks($pupil, $this->section, $this->terms[0], array_fill_keys(['MORAL', 'MYAN', 'ENG', 'MATH', 'SCI', 'SOCST', 'COMP', 'PE', 'MUSIC'], 70));
        $this->attend($pupil, 2, 8); // 80%, 2 days absent
        $principal = $this->makeStaff('principal', 'Principal', 'principal@test.local');
        app(ResultFreezeService::class)->lock($this->terms[0], $principal->user);

        // Attendance edited after the lock: the list and the card both keep the frozen 80% and 2.
        AttendanceRecord::where('student_id', $pupil->id)->where('status', 'Present')->limit(4)->update(['status' => 'Absent']);
        $list = $this->actingAs($this->homeroom->user)->get("/report-cards?section={$this->section->id}&term={$this->terms[0]->id}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Invented Locked<\/td>\s*<td[^>]*>80%<\/td>\s*<td[^>]*>2<\/td>/', $list);
        $card = app(\App\Services\ReportCardService::class)->build($pupil, $this->terms[0]);
        $this->assertSame('2', $card['daysAbsentRow'][1]);
    }

    public function test_the_seed_attendance_puts_only_the_named_students_over_the_limits(): void
    {
        $sections = collect();
        foreach (['Grade 3-A' => '3', 'Grade 4-A' => '4', 'Grade 7-A' => '7', 'Grade 8-A' => '8'] as $name => $level) {
            $section = $this->levelSection($name, $level);
            foreach (range(1, 5) as $i) {
                $this->pupil("{$name} Pupil {$i}", $section);
            }
            $sections->push($section);
        }
        // The first term ends today, so its whole 45 school days are recorded; the next starts tomorrow.
        $this->terms[0]->update(['start_date' => today()->subDays(62), 'end_date' => today()]);
        foreach (array_slice($this->terms, 1) as $i => $term) {
            $term->update(['start_date' => today()->addDays(1 + 63 * $i), 'end_date' => today()->addDays(63 * ($i + 1))]);
        }
        $method = new \ReflectionMethod(K12StructureSeeder::class, 'seedAttendance');
        $method->invoke(new K12StructureSeeder, $this->year, $sections, $this->homeroom->id);

        $this->assertSame(20 * 45, AttendanceRecord::count());
        $actions = app(ReportCardActionsService::class);
        $this->assertSame(['Grade 3-A Pupil 3', 'Grade 4-A Pupil 2', 'Grade 7-A Pupil 2'], $this->names($actions->aboveSubjectAbsence($this->terms[0]->fresh())));
        // One term of four can't break the year limit yet: no year flag so far.
        $this->assertSame([], $actions->belowMinimumAttendance($this->terms[0]->fresh()));
    }
}
