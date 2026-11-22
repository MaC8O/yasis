<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign-in already refuses accounts that are not Active, but a session opened before an
 * Admin or Registrar deactivated the account would otherwise keep working until it timed
 * out. This ends it on the very next request, whatever the session driver.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->status !== 'Active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account is not active. Please contact the Admin office.']);
        }

        return $next($request);
    }
}
