<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;

/**
 * ui-spec §7.8: registrar-scoped announcement composer/list — parity with the
 * Principal composer, used for registration/records notices.
 */
class RegistrarAnnouncementController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $registrar = $request->user()->staffProfile;

        return view('registrar.announcements.index', [
            'departments' => Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])->orderBy('name')->get(),
            'announcements' => Announcement::latest('published_at')->take(10)->get(),
            'received' => Announcement::visibleToStaff($registrar)->with('author.user')->latest('published_at')->get(),
        ]);
    }

    public function store(Request $request, AnnouncementService $service)
    {
        $data = $request->validate([
            'audience' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $service->publish($data['audience'], $data['title'], $data['body'], $request->user()->staffProfile, $request->user());

        return back()->with('status', 'Announcement published.');
    }
}
