<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // Signed-in users who open a guest-only page (/login, or / which redirects there) go to
        // their own dashboard. Laravel's default target is "/", which redirects back to /login —
        // an endless loop. Users without a role land on the calendar, open to every signed-in user.
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            $role = $request->user()?->getRoleNames()->first();

            return $role ? "/{$role}/dashboard" : '/calendar';
        });

        // End sessions of accounts deactivated after sign-in, then idle-session timeout (§3.2),
        // then funnel users with a pending admin-forced reset to the set-password screen (§3.1).
        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountIsActive::class,
            \App\Http\Middleware\EnforceSessionTimeout::class,
            \App\Http\Middleware\EnsurePasswordIsSet::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
