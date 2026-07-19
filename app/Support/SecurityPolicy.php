<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Validation\Rules\Password;

/**
 * Single source of truth for the admin-configurable security policy
 * (Settings → Security policy). Every enforcement point — login lockout,
 * idle-session timeout, password strength, forced first-login reset — reads
 * its thresholds from here so the settings screen actually governs behaviour.
 */
class SecurityPolicy
{
    /** Consecutive failed sign-ins before an account is locked. */
    public static function lockoutThreshold(): int
    {
        return max(1, (int) SystemSetting::get('lockout_threshold', '5'));
    }

    /** How long an account stays locked once the threshold is hit (minutes). */
    public static function lockoutMinutes(): int
    {
        return max(1, (int) SystemSetting::get('lockout_minutes', '15'));
    }

    /**
     * Failed sign-in attempts allowed per minute from a single IP address before that
     * IP is throttled. Account lockout ({@see lockoutThreshold()}) stops brute-forcing a
     * single account; this stops password-spraying — one failure each across many
     * accounts from one IP, which never trips any single account's lockout. Only failed
     * attempts are counted, so successful logins (e.g. many staff behind one school NAT
     * IP) are never throttled.
     */
    public static function loginThrottlePerIp(): int
    {
        return max(1, (int) SystemSetting::get('login_throttle_ip_per_min', '20'));
    }

    /** Idle time before a signed-in user is automatically logged out (minutes). */
    public static function sessionTimeoutMinutes(): int
    {
        return max(1, (int) SystemSetting::get('session_timeout_minutes', '30'));
    }

    public static function passwordMinLength(): int
    {
        return max(6, (int) SystemSetting::get('password_min_length', '8'));
    }

    public static function passwordRequiresMixed(): bool
    {
        return SystemSetting::get('password_require_mixed', '1') === '1';
    }

    public static function forceResetNewAccounts(): bool
    {
        return SystemSetting::get('force_reset_new_accounts', '1') === '1';
    }

    /** The validation rule for any new password, built from the configured policy. */
    public static function passwordRule(): Password
    {
        $rule = Password::min(self::passwordMinLength());

        return self::passwordRequiresMixed()
            ? $rule->letters()->mixedCase()->numbers()
            : $rule;
    }
}
