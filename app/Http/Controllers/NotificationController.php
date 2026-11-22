<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Personal in-app notices for the signed-in user (every role). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notices marked as read.');
    }

    /** Open a notice: mark it read and follow its link (only ever to a page in this app). */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;
        $sameHost = $url && parse_url($url, PHP_URL_HOST) === $request->getHost();

        return $sameHost ? redirect()->to($url) : redirect()->route('notifications.index');
    }
}
