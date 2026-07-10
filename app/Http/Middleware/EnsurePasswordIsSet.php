<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * §3.1/§5: while a user is flagged must_reset_password they may not reach any route
 * except the set-password screen itself and logout. Every other request is funnelled
 * back to /set-password.
 */
class EnsurePasswordIsSet
{
    /** Routes a flagged user is still allowed to reach. */
    protected array $allowed = ['password.set', 'password.set.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->must_reset_password && ! $request->routeIs($this->allowed)) {
            return redirect()->route('password.set');
        }

        return $next($request);
    }
}
