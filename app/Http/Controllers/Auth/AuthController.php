<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Support\SecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected const GENERIC_ERROR = 'These credentials do not match our records.';

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->to($this->dashboardPathFor(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request, AuditService $audit)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Per-IP defence-in-depth against password-spraying: reject before touching the
        // database once this network has produced too many failures in the window.
        $this->ensureIpNotThrottled($request);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => 'This account is temporarily locked after too many failed sign-in attempts. Try again in '
                    .now()->diffInMinutes($user->locked_until).' minute(s), or contact the Admin office.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Count the failure against this IP (60s window). Only failures are counted,
            // so legitimate sign-ins from a shared campus IP are never throttled.
            RateLimiter::hit($this->ipThrottleKey($request), 60);
            $this->registerFailedAttempt($user, $audit);

            throw ValidationException::withMessages(['email' => self::GENERIC_ERROR]);
        }

        $user = Auth::user();

        if ($user->status !== 'Active') {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Please contact the Admin office.',
            ]);
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        $request->session()->regenerate();

        $audit->log($user, 'Logged in', 'User', $user->id);

        // §3.1/§5: an admin-initiated reset forces the user through the set-password
        // screen before they can reach any other route.
        if ($user->must_reset_password) {
            return redirect()->route('password.set');
        }

        return redirect()->intended($this->dashboardPathFor($user));
    }

    /** Rate-limiter bucket keyed to the caller's IP (independent of which account is targeted). */
    protected function ipThrottleKey(Request $request): string
    {
        return 'login-ip:'.$request->ip();
    }

    /** Block the request when this IP has exceeded the per-minute failed-login budget. */
    protected function ensureIpNotThrottled(Request $request): void
    {
        $key = $this->ipThrottleKey($request);

        if (! RateLimiter::tooManyAttempts($key, SecurityPolicy::loginThrottlePerIp())) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);
        $wait = $seconds >= 60 ? ceil($seconds / 60).' minute(s)' : $seconds.' second(s)';

        throw ValidationException::withMessages([
            'email' => "Too many failed sign-in attempts from this network. Try again in {$wait}.",
        ]);
    }

    protected function registerFailedAttempt(?User $user, AuditService $audit): void
    {
        if (! $user) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $threshold = SecurityPolicy::lockoutThreshold();

        if ($attempts >= $threshold) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => now()->addMinutes(SecurityPolicy::lockoutMinutes()),
            ])->save();

            $audit->log($user, "Account locked after {$threshold} failed login attempts", 'User', $user->id);

            return;
        }

        $user->forceFill(['failed_login_attempts' => $attempts])->save();
    }

    public function logout(Request $request, AuditService $audit)
    {
        $user = Auth::user();

        if ($user) {
            $audit->log($user, 'Logged out', 'User', $user->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function dashboardPathFor($user): string
    {
        $role = $user->getRoleNames()->first();

        return $role ? "/{$role}/dashboard" : '/';
    }
}
