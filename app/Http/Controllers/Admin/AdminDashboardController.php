<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'activeUsers' => User::where('status', 'Active')->count(),
            'accountsNeedingAttention' => User::whereIn('status', ['Inactive', 'Pending'])
                ->orWhere(fn ($q) => $q->where('locked_until', '>', now()))
                ->orWhereNull('last_login_at')
                ->count(),
            'lockedAccounts' => User::where('locked_until', '>', now())->count(),
            'loginsToday' => AuditLog::where('action', 'Logged in')->whereDate('created_at', today())->count(),
            'backupStatus' => SystemSetting::get('last_backup_status', 'Not configured'),
            'activeYear' => AcademicYear::where('is_active', true)->first(),
            'recentActivity' => AuditLog::with('user')->latest('created_at')->take(8)->get(),
        ]);
    }
}
