<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Support\BuildsCalendarMonth;
use Illuminate\Http\Request;

/**
 * The read-only academic calendar available to every signed-in user. It shows
 * only Published events; Pending (unapproved) submissions never appear here.
 */
class CalendarController extends Controller
{
    use BuildsCalendarMonth;

    public function index(Request $request)
    {
        $data = $this->calendarMonthData($request, fn () => CalendarEvent::published());

        return view('calendar.index', array_merge($data, [
            'role' => $request->user()->getRoleNames()->first(),
            'types' => array_keys(CalendarEvent::TYPES),
        ]));
    }
}
