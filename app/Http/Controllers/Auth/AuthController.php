<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Support\SecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => 'This account is temporarily locked after too many failed sign-in attempts. Try again in '
                    .now()->diffInMinutes($user->locked_until).' minute(s), or contact the Admin office.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
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
