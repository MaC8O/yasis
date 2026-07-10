<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditService;
use Illuminate\Http\Request;

class TeacherAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user()->staffProfile;
        $sections = $teacher->teachingAssignments()->with('section')->get()->pluck('section')
            ->concat($teacher->homeroomSections)->unique('id')->values();

        return view('teacher.announcements.index', [
            'sections' => $sections,
            'announcements' => Announcement::where('author_id', $teacher->id)->latest('published_at')->get(),
            'received' => Announcement::visibleToStaff($teacher)->with('author.user')->latest('published_at')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $teacher = $request->user()->staffProfile;

        $announcement = Announcement::create([
            'author_id' => $teacher->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience_type' => 'Section',
            'audience_id' => $data['section_id'],
            'published_at' => now(),
        ]);

        $audit->log($request->user(), 'Published announcement', 'Announcement', $announcement->id);

        return back()->with('status', 'Announcement published to your class.');
    }
}
