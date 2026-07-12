<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * §6.7 System Settings — institution-wide configuration, grouped into real
 * operational areas: institution profile, localization, notifications & email
 * (SMTP), security policy, and maintenance. The schema below drives both
 * validation and the rendered form so the two never drift.
 */
class SystemSettingsController extends Controller
{
    /**
     * Setting groups → fields. Each field: type, default, label, help, and
     * (for select) options. Types: text, textarea, email, url, number, bool, select.
     */
    public function schema(): array
    {
        return [
            'Institution profile' => [
                'icon' => 'building',
                'blurb' => 'Appears on report cards, transcripts, notices and the app header.',
                'fields' => [
                    'institution_name' => ['type' => 'text', 'label' => 'Institution name', 'default' => 'Yangon Adventist Seminary', 'placeholder' => 'Full legal name'],
                    'institution_short_name' => ['type' => 'text', 'label' => 'Short name / acronym', 'default' => 'YASIS'],
                    'institution_email' => ['type' => 'email', 'label' => 'Contact email', 'default' => ''],
                    'institution_phone' => ['type' => 'text', 'label' => 'Contact phone', 'default' => ''],
                    'institution_website' => ['type' => 'url', 'label' => 'Website', 'default' => ''],
                    'principal_name' => ['type' => 'text', 'label' => 'Principal', 'default' => ''],
                    'established_year' => ['type' => 'number', 'label' => 'Established year', 'default' => '1975'],
                    'institution_address' => ['type' => 'textarea', 'label' => 'Address', 'default' => '', 'full' => true],
                ],
            ],
            'Localization' => [
                'icon' => 'globe',
                'blurb' => 'Timezone and formats used across the system.',
                'fields' => [
                    'timezone' => ['type' => 'select', 'label' => 'Timezone', 'default' => 'Asia/Yangon', 'options' => ['Asia/Yangon' => 'Yangon (MMT +6:30)', 'Asia/Bangkok' => 'Bangkok (+7)', 'Asia/Singapore' => 'Singapore (+8)', 'UTC' => 'UTC']],
                    'date_format' => ['type' => 'select', 'label' => 'Date format', 'default' => 'M j, Y', 'options' => ['M j, Y' => 'Jul 12, 2026', 'd/m/Y' => '12/07/2026', 'Y-m-d' => '2026-07-12', 'd M Y' => '12 Jul 2026']],
                    'week_start' => ['type' => 'select', 'label' => 'Week starts on', 'default' => 'Sunday', 'options' => ['Sunday' => 'Sunday', 'Monday' => 'Monday']],
                    'academic_language' => ['type' => 'select', 'label' => 'Primary language', 'default' => 'English', 'options' => ['English' => 'English', 'Myanmar' => 'Myanmar (မြန်မာ)']],
                ],
            ],
            'Notifications & email' => [
                'icon' => 'bell',
                'blurb' => 'Outbound email (SMTP) and which events trigger notifications.',
                'fields' => [
                    'notifications_enabled' => ['type' => 'bool', 'label' => 'Email notifications enabled', 'default' => '1', 'help' => 'Master switch for all outbound email.'],
                    'notify_absence' => ['type' => 'bool', 'label' => 'Absence alerts to guardians', 'default' => '1'],
                    'notify_leave_decision' => ['type' => 'bool', 'label' => 'Leave request decisions to staff', 'default' => '1'],
                    'notify_account' => ['type' => 'bool', 'label' => 'Account & credential alerts', 'default' => '1'],
                    'notify_announcement' => ['type' => 'bool', 'label' => 'Announcement emails', 'default' => '1'],
                    'smtp_host' => ['type' => 'text', 'label' => 'SMTP host', 'default' => ''],
                    'smtp_port' => ['type' => 'number', 'label' => 'SMTP port', 'default' => '587'],
                    'smtp_encryption' => ['type' => 'select', 'label' => 'Encryption', 'default' => 'tls', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None']],
                    'smtp_username' => ['type' => 'text', 'label' => 'SMTP username', 'default' => ''],
                    'smtp_from_address' => ['type' => 'email', 'label' => 'From address', 'default' => ''],
                ],
            ],
            'Security policy' => [
                'icon' => 'shield',
                'blurb' => 'Session, lockout and password rules (§3.1–3.3).',
                'fields' => [
                    'session_timeout_minutes' => ['type' => 'number', 'label' => 'Session timeout (minutes)', 'default' => '30', 'help' => 'Idle users are signed out after this many minutes.'],
                    'lockout_threshold' => ['type' => 'number', 'label' => 'Failed logins before lockout', 'default' => '5'],
                    'lockout_minutes' => ['type' => 'number', 'label' => 'Lockout duration (minutes)', 'default' => '15'],
                    'password_min_length' => ['type' => 'number', 'label' => 'Minimum password length', 'default' => '8'],
                    'password_require_mixed' => ['type' => 'bool', 'label' => 'Require upper, lower & number', 'default' => '1'],
                    'force_reset_new_accounts' => ['type' => 'bool', 'label' => 'Force password reset on first login', 'default' => '1'],
                ],
            ],
            'Maintenance' => [
                'icon' => 'wrench',
                'blurb' => 'Put the portal into a read-only maintenance state for non-admins.',
                'fields' => [
                    'maintenance_mode' => ['type' => 'bool', 'label' => 'Maintenance mode', 'default' => '0', 'help' => 'When on, non-admin users see the maintenance notice.'],
                    'maintenance_message' => ['type' => 'textarea', 'label' => 'Maintenance notice', 'default' => 'The system is undergoing scheduled maintenance. Please check back shortly.', 'full' => true],
                ],
            ],
        ];
    }

    public function index()
    {
        $settings = [];
        foreach ($this->allFields() as $key => $field) {
            $settings[$key] = SystemSetting::get($key, $field['default'] ?? '');
        }

        $logoPath = SystemSetting::get('institution_logo_path');

        return view('admin.settings.index', [
            'schema' => $this->schema(),
            'settings' => $settings,
            'smtpConfigured' => filled(SystemSetting::get('smtp_host')),
            'logoUrl' => $logoPath ? Storage::url($logoPath) : null,
        ]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $fields = $this->allFields();
        $rules = $this->rules($fields);
        $rules['institution_logo'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
        $data = $request->validate($rules, [
            'institution_logo.max' => 'The logo may not be larger than 4 MB.',
        ]);

        foreach ($fields as $key => $field) {
            if ($field['type'] === 'bool') {
                SystemSetting::set($key, $request->boolean($key) ? '1' : '0');
            } else {
                SystemSetting::set($key, $data[$key] ?? '');
            }
        }

        $this->handleLogo($request);

        $audit->log($request->user(), 'Updated system settings', 'SystemSetting', null);

        return back()->with('status', 'System settings saved.');
    }

    /** A new upload replaces the old logo; the remove checkbox clears it. */
    protected function handleLogo(Request $request): void
    {
        $old = SystemSetting::get('institution_logo_path');

        if ($request->hasFile('institution_logo')) {
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('institution_logo')->store('logos', 'public');
            SystemSetting::set('institution_logo_path', $path);

            return;
        }

        if ($request->boolean('remove_logo') && $old) {
            Storage::disk('public')->delete($old);
            SystemSetting::set('institution_logo_path', '');
        }
    }

    /** Test SMTP reachability without sending mail or risking a long hang. */
    public function testSmtp(Request $request)
    {
        $host = SystemSetting::get('smtp_host');
        $port = (int) SystemSetting::get('smtp_port', '587');

        if (blank($host)) {
            return back()->withErrors(['smtp' => 'Set an SMTP host before testing the connection.']);
        }

        $conn = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($conn) {
            fclose($conn);

            return back()->with('status', "SMTP connection to {$host}:{$port} succeeded.");
        }

        return back()->withErrors(['smtp' => "Could not reach {$host}:{$port} — {$errstr} (error {$errno})."]);
    }

    protected function allFields(): array
    {
        $fields = [];
        foreach ($this->schema() as $group) {
            foreach ($group['fields'] as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    protected function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'email' => ['nullable', 'email', 'max:255'],
                'url' => ['nullable', 'url', 'max:255'],
                'number' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'bool' => ['nullable', 'boolean'],
                'select' => ['nullable', 'string', 'max:100'],
                'textarea' => ['nullable', 'string', 'max:1000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }
}
