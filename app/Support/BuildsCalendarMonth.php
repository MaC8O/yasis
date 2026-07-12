<?php

namespace App\Support;

use App\Models\CalendarEvent;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

/**
 * Shared month-grid assembly for every calendar surface (read-only, Registrar,
 * Admin). Callers pass a $baseQuery closure that returns a fresh query — e.g.
 * CalendarEvent::published() for the all-user view, or CalendarEvent::query()
 * for the management views — so visibility rules stay in the controller.
 */
trait BuildsCalendarMonth
{
    protected function calendarMonthData(Request $request, Closure $baseQuery): array
    {
        $cursor = Carbon::createFromDate(
            (int) $request->integer('year', now()->year),
            (int) $request->integer('month', now()->month),
            1
        )->startOfMonth();

        $monthEvents = $baseQuery()->forMonth($cursor->year, $cursor->month)
            ->orderBy('start_date')->orderBy('title')->get();

        $upcoming = $baseQuery()->where(function ($q) {
            $q->where('start_date', '>=', today())->orWhere('end_date', '>=', today());
        })->orderBy('start_date')->limit(8)->get();

        return [
            'cursor' => $cursor,
            'monthEvents' => $monthEvents,
            'eventsByDay' => $this->mapEventsToDays($monthEvents, $cursor),
            'upcoming' => $upcoming,
        ];
    }

    /** Bucket each event under every day-of-month it touches, for the grid. */
    protected function mapEventsToDays($events, Carbon $cursor): array
    {
        $monthStart = $cursor->copy()->startOfMonth();
        $monthEnd = $cursor->copy()->endOfMonth();
        $byDay = [];

        foreach ($events as $event) {
            $from = $event->start_date->greaterThan($monthStart) ? $event->start_date->copy() : $monthStart->copy();
            $to = $event->effective_end->lessThan($monthEnd) ? $event->effective_end->copy() : $monthEnd->copy();

            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $byDay[$d->day][] = $event;
            }
        }

        return $byDay;
    }
}
