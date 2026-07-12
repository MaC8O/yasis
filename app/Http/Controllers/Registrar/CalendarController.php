<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Services\AuditService;
use App\Support\BuildsCalendarMonth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * §7 Registrar academic calendar. The Registrar's office owns much of the
 * academic schedule (enrollment dates, exam schedules, grade-submission
 * deadlines, add/drop periods, orientation, graduation events), so the
 * Registrar can create and edit events — but publishing to all users, and
 * deleting a published event, require Admin approval. New events are submitted
 * as Pending; the Registrar may delete their own still-Pending submissions.
 */
class CalendarController extends Controller
{
    use BuildsCalendarMonth;

    public function index(Request $request)
    {
        $data = $this->calendarMonthData($request, fn () => CalendarEvent::query());

        return view('calendar.manage', array_merge($data, [
            'pending' => CalendarEvent::pending()->with('creator')->orderBy('start_date')->get(),
            'academicYears' => AcademicYear::orderByDesc('year_label')->get(),
            'activeYear' => AcademicYear::where('is_active', true)->first(),
            'types' => array_keys(CalendarEvent::TYPES),
            'ctx' => [
                'role' => 'registrar',
                'base' => url('registrar/calendar'),
                'storeUrl' => route('registrar.calendar.store'),
                'canPublish' => false,
                'canDeleteAny' => false,
                'newStatusNote' => 'Events you add are submitted for Admin approval before they appear on everyone’s calendar.',
            ],
        ]));
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['created_by_role'] = 'registrar';
        $data['status'] = CalendarEvent::STATUS_PENDING;

        $event = CalendarEvent::create($data);
        $audit->log($request->user(), 'Submitted calendar event for approval', 'CalendarEvent', $event->id);

        return back()->with('status', "\"{$event->title}\" submitted for Admin approval.");
    }

    public function update(Request $request, CalendarEvent $calendarEvent, AuditService $audit)
    {
        $calendarEvent->update($this->validated($request));
        $audit->log($request->user(), 'Edited calendar event', 'CalendarEvent', $calendarEvent->id);

        return back()->with('status', "\"{$calendarEvent->title}\" updated.");
    }

    /** Registrar may remove only their own submissions that are still awaiting approval. */
    public function destroy(Request $request, CalendarEvent $calendarEvent, AuditService $audit)
    {
        abort_unless(
            $calendarEvent->status === CalendarEvent::STATUS_PENDING && $calendarEvent->created_by === $request->user()->id,
            403,
            'Published events, and events created by others, can only be removed by an Admin.'
        );

        $id = $calendarEvent->id;
        $title = $calendarEvent->title;
        $calendarEvent->delete();
        $audit->log($request->user(), 'Withdrew calendar event submission', 'CalendarEvent', $id);

        return back()->with('status', "\"{$title}\" withdrawn.");
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'event_type' => ['required', Rule::in(array_keys(CalendarEvent::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:500'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ]);
    }
}
