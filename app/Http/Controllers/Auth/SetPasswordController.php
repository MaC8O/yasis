<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Forced set-password screen (§3.1). After an admin resets a user's credentials
 * (must_reset_password = true) the EnsurePasswordIsSet middleware funnels the user
 * here on their next request and blocks every other route until they choose a new
 * password meeting the policy (§3.1: min 8, letters + digits).
 */
class SetPasswordController extends Controller
{
    public function show()
    {
        // A user who no longer needs a reset has no business on this screen.
        if (! Auth::user()->must_reset_password) {
            return redirect()->to($this->dashboardPathFor(Auth::user()));
        }

        return view('auth.set-password');
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = Auth::user();

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_reset_password' => false,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        $audit->log($user, 'Set a new password (forced reset)', 'User', $user->id);

        return redirect()->to($this->dashboardPathFor($user))
            ->with('status', 'Your new password is set. Welcome back.');
    }

    protected function dashboardPathFor($user): string
    {
        $role = $user->getRoleNames()->first();

        return $role ? "/{$role}/dashboard" : '/';
    }
}
