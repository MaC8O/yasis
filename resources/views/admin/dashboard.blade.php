<x-app-layout title="Admin Dashboard" subtitle="Manage system access, users, roles, credentials, configuration, backups, and data retention." badge="Admin" role="admin">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Active users" color="blue">{{ number_format($activeUsers) }}</x-stat-tile>
        <x-stat-tile label="Logins today" color="blue">{{ number_format($loginsToday) }}</x-stat-tile>
        <x-stat-tile label="Accounts to action" color="yellow">{{ number_format($accountsNeedingAttention) }}</x-stat-tile>
        <x-stat-tile label="Last backup" color="pink">{{ $backupStatus }}</x-stat-tile>
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
            <a href="{{ route('admin.users.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-xl px-5 py-2.5 text-sm">
                Manage users
            </a>
        </x-card>

        <x-card title="Administrative shortcuts">
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm font-semibold">
                <a href="{{ route('admin.teacher-assignments.index') }}" class="block hover:underline">Teacher Class Assignment</a>
                <a href="{{ route('admin.academic-year.index') }}" class="block hover:underline">Academic Year</a>
                <a href="{{ route('admin.calendar.index') }}" class="block hover:underline">Academic Calendar</a>
                <a href="{{ route('admin.grade-scale.index') }}" class="block hover:underline">Grade Scale</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="block hover:underline">Audit Logs</a>
                <a href="{{ route('admin.backup.index') }}" class="block hover:underline">Data &amp; Backup</a>
                <a href="{{ route('admin.settings.index') }}" class="block hover:underline">System Settings</a>
            </div>
        </x-card>
    </div>

    <x-card title="Recent activity" subtitle="Security and system activity overview.">
        <table class="w-full text-sm">
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
                        <td class="py-2.5">{{ $log->created_at->format('H:i') }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $log->category }}</td>
                        <td class="py-2.5">{{ $log->user?->name ?? '—' }}</td>
                        <td class="py-2.5">{{ $log->action }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
