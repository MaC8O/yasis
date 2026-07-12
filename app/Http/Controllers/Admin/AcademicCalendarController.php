<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Services\AuditService;
use App\Support\BuildsCalendarMonth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * §6.7 Academic calendar — Admin owns it end to end: create, edit, delete, and
 * approve/publish any event (including Registrar submissions). Admin-created
 * events are published immediately; a Registrar submission arrives Pending and
 * becomes visible to all users once the Admin approves it.
 */
class AcademicCalendarController extends Controller
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
                'role' => 'admin',
                'base' => url('admin/calendar'),
                'storeUrl' => route('admin.calendar.store'),
                'canPublish' => true,
                'canDeleteAny' => true,
                'newStatusNote' => 'Events you add here are published immediately.',
            ],
        ]));
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['created_by_role'] = 'admin';
        $data['status'] = CalendarEvent::STATUS_PUBLISHED;
        $data['published_by'] = $request->user()->id;
        $data['published_at'] = now();

        $event = CalendarEvent::create($data);
        $audit->log($request->user(), 'Added calendar event', 'CalendarEvent', $event->id);

        return back()->with('status', "\"{$event->title}\" added and published.");
    }

    public function update(Request $request, CalendarEvent $calendarEvent, AuditService $audit)
    {
        $calendarEvent->update($this->validated($request));
        $audit->log($request->user(), 'Edited calendar event', 'CalendarEvent', $calendarEvent->id);

        return back()->with('status', "\"{$calendarEvent->title}\" updated.");
    }

    public function publish(Request $request, CalendarEvent $calendarEvent, AuditService $audit)
    {
        $calendarEvent->update([
            'status' => CalendarEvent::STATUS_PUBLISHED,
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);
        $audit->log($request->user(), 'Approved & published calendar event', 'CalendarEvent', $calendarEvent->id);

        return back()->with('status', "\"{$calendarEvent->title}\" approved — now visible to all users.");
    }

    public function unpublish(Request $request, CalendarEvent $calendarEvent, AuditService $audit)
    {
        $calendarEvent->update([
            'status' => CalendarEvent::STATUS_PENDING,
            'published_by' => null,
            'published_at' => null,
        ]);
        $audit->log($request->user(), 'Unpublished calendar event', 'CalendarEvent', $calendarEvent->id);

        return back()->with('status', "\"{$calendarEvent->title}\" unpublished — hidden from users until re-approved.");
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent, AuditService $audit)
    {
        $id = $calendarEvent->id;
        $title = $calendarEvent->title;
        $calendarEvent->delete();
        $audit->log($request->user(), 'Deleted calendar event', 'CalendarEvent', $id);

        return back()->with('status', "\"{$title}\" removed from the calendar.");
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
