<?php

namespace App\Http\Middleware;

use App\Support\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * §3.2 Session security: sign a user out after a configurable period of
 * inactivity (Settings → Security policy → Session timeout). Activity is the
 * time since the user's last request; the configured value also drives the
 * session cookie's own lifetime so the framework and this check agree.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeout = SecurityPolicy::sessionTimeoutMinutes();

        // Keep the framework session lifetime aligned with the policy.
        config(['session.lifetime' => $timeout]);

        $last = (int) $request->session()->get('last_activity_at', 0);
        $idleSeconds = now()->timestamp - $last;

        if ($last > 0 && $idleSeconds >= $timeout * 60) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => "You were signed out after {$timeout} minutes of inactivity. Please sign in again."]);
        }

        $request->session()->put('last_activity_at', now()->timestamp);

        return $next($request);
    }
}
