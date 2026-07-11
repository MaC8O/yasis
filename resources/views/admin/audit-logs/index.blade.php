<x-app-layout title="Audit Logs" subtitle="Immutable non-repudiation trail of every critical action." badge="Admin" role="admin">
    <x-card>
        <form method="GET" class="flex flex-wrap gap-4 items-end mb-4">
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-semibold mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by user, action, or entity"
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
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
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Time</th>
                        <th class="py-2 font-semibold">User</th>
                        <th class="py-2 font-semibold">Role</th>
                        <th class="py-2 font-semibold">Action</th>
                        <th class="py-2 font-semibold">Entity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $log->created_at->format('M j, Y H:i') }}</td>
                            <td class="py-2.5">{{ $log->user?->name ?? '—' }}</td>
                            <td class="py-2.5 text-neutral-500">{{ ucwords(str_replace('_', ' ', $log->role)) }}</td>
                            <td class="py-2.5">{{ $log->action }}</td>
                            <td class="py-2.5 text-neutral-500">{{ $log->entity_type }}{{ $log->entity_id ? " #{$log->entity_id}" : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-neutral-400">No activity recorded yet.</td></tr>
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
