<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\ReportRule;
use App\Models\Term;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The days the school meets in a period (sdp_doc/YASIS_Academic_and_Fee_Rules.md §8): every date
 * from the start to the end, on the days of the week the school meets (`school_week`, a report
 * rule, Monday to Friday by default), minus the published Academic Calendar events of type
 * Holiday or Term Break that fall inside it. Exams, meetings and activities are school days.
 *
 * A holiday added to the calendar later changes the count for open terms; locked terms keep their
 * frozen figures.
 */
class SchoolDays
{
    /** ISO day numbers (Monday 1 ... Sunday 7) per `school_week` option. */
    public const WEEKS = [
        'mon_fri' => [1, 2, 3, 4, 5],
        'mon_sat' => [1, 2, 3, 4, 5, 6],
        'sun_thu' => [7, 1, 2, 3, 4],
    ];

    /** Calendar event types on which the school doesn't meet. */
    public const CLOSED_TYPES = ['Holiday', 'Term Break'];

    /** @return list<int> */
    public static function weekDays(): array
    {
        return self::WEEKS[ReportRule::value('school_week', 'mon_fri')] ?? self::WEEKS['mon_fri'];
    }

    /**
     * The school days from $from to $to (both included), as Y-m-d strings.
     *
     * @return list<string>
     */
    public function between(CarbonInterface $from, CarbonInterface $to): array
    {
        $week = self::weekDays();
        $closed = $this->closedDates($from, $to);
        $days = [];
        for ($day = Carbon::parse($from)->startOfDay(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            if (in_array($day->dayOfWeekIso, $week, true) && ! isset($closed[$date])) {
                $days[] = $date;
            }
        }

        return $days;
    }

    /** The term's school days, from its start date to its end date. */
    public function inTerm(Term $term): int
    {
        return count($this->between($term->start_date, $term->end_date));
    }

    /** The school year's school days: its terms added up, so the gaps between terms don't count. */
    public function inYear(int $academicYearId): int
    {
        return Term::where('academic_year_id', $academicYearId)->get()->sum(fn (Term $t) => $this->inTerm($t));
    }

    /** @return array<string, true> the dates published Holiday and Term Break events cover */
    private function closedDates(CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = [];
        $events = CalendarEvent::where('status', CalendarEvent::STATUS_PUBLISHED)->whereIn('event_type', self::CLOSED_TYPES)
            ->whereDate('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $from)->orWhere(fn ($q) => $q->whereNull('end_date')->whereDate('start_date', '>=', $from)))
            ->get(['start_date', 'end_date']);
        foreach ($events as $event) {
            $end = Carbon::parse($event->end_date ?? $event->start_date);
            for ($day = Carbon::parse($event->start_date); $day->lte($end); $day->addDay()) {
                $dates[$day->toDateString()] = true;
            }
        }

        return $dates;
    }
}
