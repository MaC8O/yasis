<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    protected array $keys = [
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_from_address',
        'notifications_enabled', 'calendar_note',
    ];

    public function index()
    {
        $settings = [];
        foreach ($this->keys as $key) {
            $settings[$key] = SystemSetting::get($key, '');
        }

        return view('admin.settings.index', ['settings' => $settings]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'string', 'max:10'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email'],
            'notifications_enabled' => ['nullable', 'boolean'],
            'calendar_note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['notifications_enabled'] = $request->boolean('notifications_enabled') ? '1' : '0';

        foreach ($this->keys as $key) {
            SystemSetting::set($key, $data[$key] ?? '');
        }

        $audit->log($request->user(), 'Updated system settings', 'SystemSetting', null);

        return back()->with('status', 'System settings saved.');
    }
}
