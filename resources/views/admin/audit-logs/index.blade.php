<x-app-layout title="Audit Logs" subtitle="Immutable non-repudiation trail of every critical action." badge="Admin" role="admin">
    <x-card>
        <form method="GET" class="flex flex-wrap gap-4 items-end mb-4">
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-semibold mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by user, action, or entity"
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Category</label>
                <select name="category" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                    class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                    class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Filter</button>
            <a href="{{ route('admin.audit-logs.export', request()->query()) }}"
               class="border border-[#1F573D] text-[#1F573D] font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-[#1F573D]/5">Export CSV</a>
            <a href="{{ route('admin.audit-logs.verify') }}"
               class="border border-[#1F573D] text-[#1F573D] font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-[#1F573D]/5">Verify integrity</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Time</th>
                        <th class="py-2 font-semibold">Category</th>
                        <th class="py-2 font-semibold">User</th>
                        <th class="py-2 font-semibold">Role</th>
                        <th class="py-2 font-semibold">IP</th>
                        <th class="py-2 font-semibold">Action</th>
                        <th class="py-2 font-semibold">Entity</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $categoryColors = [
                            'Authentication' => 'bg-[#DCE7F9] text-[#1e3a6e]',
                            'User Management' => 'bg-[#F5E4A8] text-[#5c4a0a]',
                            'Academic Records' => 'bg-[#D7ECD9] text-[#1f4d2c]',
                            'Grades & Assessment' => 'bg-[#E4DAF3] text-[#4a2d6e]',
                            'Attendance & Leave' => 'bg-[#F6D9D9] text-[#7a2020]',
                            'Finance' => 'bg-[#D9EEF0] text-[#155e63]',
                            'System' => 'bg-neutral-200 text-neutral-700',
                        ];
                    @endphp
                    @forelse ($logs as $log)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5 whitespace-nowrap">{{ $log->created_at->format('M j, Y H:i') }}</td>
                            <td class="py-2.5">
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $categoryColors[$log->category] ?? 'bg-neutral-200 text-neutral-700' }}">{{ $log->category }}</span>
                            </td>
                            <td class="py-2.5">{{ $log->user?->name ?? '—' }}</td>
                            <td class="py-2.5 text-neutral-500">{{ ucwords(str_replace('_', ' ', $log->role)) }}</td>
                            <td class="py-2.5 text-neutral-500 whitespace-nowrap font-mono text-xs">{{ $log->ip_address ?? '—' }}</td>
                            <td class="py-2.5">
                                {{ $log->action }}
                                @if (! empty($log->details['changes']))
                                    <details class="mt-1">
                                        <summary class="text-xs text-[#1F573D] cursor-pointer select-none">{{ count($log->details['changes']) }} value change(s)</summary>
                                        <ul class="mt-1 space-y-0.5 text-xs text-neutral-500">
                                            @foreach ($log->details['changes'] as $change)
                                                <li>
                                                    Assessment #{{ $change['assessment_id'] ?? '?' }}, student #{{ $change['student_id'] ?? '?' }}:
                                                    <span class="font-mono">{{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                            <td class="py-2.5 text-neutral-500">{{ $log->entity_type }}{{ $log->entity_id ? " #{$log->entity_id}" : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-neutral-400">No activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
            <x-per-page :default="20" />
            <div>{{ $logs->links() }}</div>
        </div>
    </x-card>
</x-app-layout>
