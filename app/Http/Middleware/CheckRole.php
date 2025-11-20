<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Check if user is logged in
        if (! $request->user()) {
            return redirect('/login');
        }

        // 2. Check if the user has the required role
        if ($request->user()->role->name !== $role) {
            // If not, show a 403 Forbidden error
            abort(403, 'You are not authorized to access this page.');
        }

        return $next($request);
    }
}
