<x-app-layout title="Admin Dashboard" subtitle="Manage system access, users, roles, credentials, configuration, backups, and data retention." badge="Admin" role="admin">
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Active users" :href="route('admin.users.index')" color="blue">{{ number_format($activeUsers) }}</x-stat-tile>
        <x-stat-tile label="Logins today" :href="route('admin.audit-logs.index')" color="blue">{{ number_format($loginsToday) }}</x-stat-tile>
        <x-stat-tile label="Accounts to action" :href="route('admin.users.index')" color="yellow">{{ number_format($accountsNeedingAttention) }}</x-stat-tile>
        <x-stat-tile label="Last backup" :href="route('admin.backup.index')" color="pink">{{ $backupStatus }}</x-stat-tile>
    </div>

    {{-- People: who's in the system and their account health --}}
    <div>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-400 mb-3">People &amp; access</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Accounts by role" subtitle="Distribution of users across the nine RBAC roles.">
                <x-chart.bar-list :items="$usersByRole" label-width="w-28" />
            </x-card>
            <x-card title="Account status" subtitle="Health of all user accounts at a glance.">
                <x-chart.donut :segments="$accountStatus" center-label="accounts" />
            </x-card>
        </div>
    </div>

    {{-- Activity: what's happening across the system --}}
    <div>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-400 mb-3">System activity</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Sign-ins — last 14 days" subtitle="Successful logins per day.">
                <x-chart.trend :points="$loginTrend" />
            </x-card>
            <x-card title="Activity by category — last 30 days" subtitle="Audited actions grouped by area of the system.">
                <x-chart.bar-list :items="$activityByCategory" label-width="w-40" color="#2f6fb0" />
            </x-card>
        </div>
    </div>

    {{-- Controls + recent trail --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="System access controls" subtitle="Role-based access and immutable audit logging.">
            <div class="flex flex-wrap gap-2">
                <x-badge color="green">RBAC enforced — 9 roles</x-badge>
                <x-badge color="blue">Immutable audit log</x-badge>
                <x-badge color="yellow">Active year: {{ $activeYear?->year_label ?? 'Not set' }}</x-badge>
                @if ($lockedAccounts > 0)
                    <x-badge color="pink">{{ $lockedAccounts }} account(s) locked</x-badge>
                @endif
            </div>
            <a href="{{ route('admin.users.index') }}" class="inline-block mt-4 bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark transition-colors">
                Manage users
            </a>
        </x-card>

        <x-card title="Administrative shortcuts">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm font-semibold">
                @foreach ([
                    ['admin.teacher-assignments.index', 'Teacher Class Assignment', 'layers'],
                    ['admin.academic-year.index', 'Academic Year', 'calendar'],
                    ['admin.calendar.index', 'Academic Calendar', 'calendar'],
                    ['admin.grade-scale.index', 'Grade Scale', 'list'],
                    ['admin.audit-logs.index', 'Audit Logs', 'shield'],
                    ['admin.backup.index', 'Data & Backup', 'database'],
                    ['admin.settings.index', 'System Settings', 'sliders'],
                ] as [$route, $label, $icon])
                    <a href="{{ route($route) }}" class="group flex items-center gap-3 rounded-xl border border-neutral-200 px-3 py-2.5 hover:border-brand hover:bg-brand-soft transition-colors">
                        <x-icon :name="$icon" class="w-4 h-4 text-brand" />
                        <span class="flex-1">{{ $label }}</span>
                        <x-icon name="chevron-right" class="w-4 h-4 text-neutral-300 group-hover:text-brand" />
                    </a>
                @endforeach
            </div>
        </x-card>
    </div>

    <x-card title="Recent activity" subtitle="Security and system activity overview.">
        <table class="rt w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Time</th>
                    <th class="py-2 font-semibold">Category</th>
                    <th class="py-2 font-semibold">User</th>
                    <th class="py-2 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentActivity as $log)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5 tabular-nums" data-label="Time">{{ $log->created_at->format('H:i') }}</td>
                        <td class="py-2.5 text-neutral-500" data-label="Category">{{ $log->category }}</td>
                        <td class="py-2.5" data-label="User">{{ $log->user?->name ?? '—' }}</td>
                        <td class="py-2.5" data-label="Action">{{ $log->action }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
