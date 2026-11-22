<?php

namespace App\Http\Controllers\Leadership;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;

/**
 * The announcement composer shared by the Principal and the VP (Academic): the same screen, the
 * same audiences (everyone, staff, guardians, students, or one department) and the same service.
 *
 * Who may publish is enforced by the route groups: /principal/* requires the principal role and
 * /vp_academic/* the vp_academic role, so every other role gets 403 on both. Each role posts to
 * its own route; the portal is taken from the route name, never from the request body.
 */
class AnnouncementComposerController extends Controller
{
    private const PORTALS = ['principal', 'vp_academic'];

    public function index(Request $request)
    {
        return view('leadership.announcements.index', [
            'portal' => $this->portal($request),
            'departments' => Department::whereIn('level', ['Secondary', 'Primary', 'Early Years'])->orderBy('name')->get(),
            'announcements' => Announcement::latest('published_at')->take(10)->get(),
        ]);
    }

    public function store(Request $request, AnnouncementService $service)
    {
        $this->portal($request);

        $data = $request->validate([
            'audience' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $service->publish($data['audience'], $data['title'], $data['body'], $request->user()->staffProfile, $request->user());

        return back()->with('status', 'Announcement published.');
    }

    /** "principal" or "vp_academic", from the route this request came in on. */
    private function portal(Request $request): string
    {
        $portal = strtok((string) $request->route()?->getName(), '.');
        abort_unless(in_array($portal, self::PORTALS, true), 404);

        return $portal;
    }
}
