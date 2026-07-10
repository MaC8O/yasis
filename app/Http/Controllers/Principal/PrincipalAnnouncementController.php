<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Services\AuditService;
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

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'audience' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $principal = $request->user()->staffProfile;

        if (in_array($data['audience'], ['All', 'Staff', 'Guardians', 'Students'])) {
            $audienceType = $data['audience'] === 'All' ? 'School' : $data['audience'];
            $audienceId = null;
        } else {
            $department = Department::where('name', $data['audience'])->firstOrFail();
            $audienceType = 'Department';
            $audienceId = $department->id;
        }

        $announcement = Announcement::create([
            'author_id' => $principal->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience_type' => $audienceType,
            'audience_id' => $audienceId,
            'published_at' => now(),
        ]);

        $audit->log($request->user(), 'Published announcement', 'Announcement', $announcement->id);

        return back()->with('status', 'Announcement published.');
    }
}
