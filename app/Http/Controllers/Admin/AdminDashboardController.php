<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

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
            'backupStatus' => SystemSetting::get('last_backup_status', 'Automated'),
            'activeYear' => AcademicYear::where('is_active', true)->first(),
            'recentActivity' => AuditLog::with('user')->latest('created_at')->take(8)->get(),
            'loginTrend' => $this->loginTrend(),
            'usersByRole' => Role::withCount('users')->orderByDesc('users_count')->get()
                ->map(fn ($role) => ['label' => ucwords(str_replace('_', ' ', $role->name)), 'value' => $role->users_count]),
            'accountStatus' => $this->accountStatusSegments(),
            'activityByCategory' => $this->activityByCategory(),
        ]);
    }

    /** Sign-ins per day for the last 14 days, zero-filled. */
    protected function loginTrend(): array
    {
        $counts = AuditLog::where('action', 'Logged in')
            ->where('created_at', '>=', today()->subDays(13))
            ->get()
            ->groupBy(fn ($log) => $log->created_at->toDateString())
            ->map->count();

        $trend = [];
        for ($d = 13; $d >= 0; $d--) {
            $day = today()->subDays($d);
            $trend[] = ['label' => $day->format('M j'), 'value' => $counts[$day->toDateString()] ?? 0];
        }

        return $trend;
    }

    /** Account health as donut segments — Active / Pending / Inactive / Locked. */
    protected function accountStatusSegments(): array
    {
        $locked = User::where('locked_until', '>', now())->count();

        return collect([
            ['label' => 'Active', 'value' => User::where('status', 'Active')->count(), 'color' => '#2E8B57'],
            ['label' => 'Pending', 'value' => User::where('status', 'Pending')->count(), 'color' => '#C9A227'],
            ['label' => 'Inactive', 'value' => User::where('status', 'Inactive')->count(), 'color' => '#9aa0a6'],
            ['label' => 'Locked', 'value' => $locked, 'color' => '#c0392b'],
        ])->all();
    }

    /**
     * Audit activity in the last 30 days grouped into the same categories the
     * Audit Logs page uses — shows where system activity is concentrated.
     */
    protected function activityByCategory(): array
    {
        $counts = AuditLog::where('created_at', '>=', now()->subDays(30))
            ->get(['action', 'entity_type'])
            ->groupBy(fn ($log) => $log->category)
            ->map->count();

        return collect(AuditLog::CATEGORIES)
            ->map(fn ($cat) => ['label' => $cat, 'value' => $counts[$cat] ?? 0])
            ->filter(fn ($row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }
}
