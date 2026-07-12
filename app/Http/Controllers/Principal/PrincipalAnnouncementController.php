<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;

class PrincipalAnnouncementController extends Controller
{
    public function index()
    {
        return view('principal.announcements.index', [
            'departments' => Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])->orderBy('name')->get(),
            'announcements' => Announcement::latest('published_at')->take(10)->get(),
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
