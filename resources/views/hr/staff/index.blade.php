<x-app-layout title="Staff Records" subtitle="All employees — teaching and non-teaching — with role, department, and status." badge="{{ $staff->count() }} staff" role="hr_office">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, ID, or role" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Department</label>
                <select name="department" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department'] ?? '') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Filter</button>
                <a href="{{ route('hr_office.staff.create') }}" class="flex-1 text-center bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm">+ Add staff</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Staff</th>
                    <th class="py-2 font-semibold">Role</th>
                    <th class="py-2 font-semibold">Department</th>
                    <th class="py-2 font-semibold">Joined</th>
                    <th class="py-2 font-semibold">Status</th>
                    <th class="py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $member)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">
                            <div class="font-semibold">{{ $member->user->name }}</div>
                            <div class="text-xs text-neutral-400">{{ $member->staff_id_number }}</div>
                        </td>
                        <td class="py-2.5">{{ $member->job_title }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $member->department?->name ?? '—' }}</td>
                        <td class="py-2.5">{{ $member->joined_date->format('d M Y') }}</td>
                        <td class="py-2.5">
                            <x-badge :color="$member->status === 'Active' ? 'green' : ($member->status === 'On Leave' ? 'blue' : ($member->status === 'Probation' ? 'yellow' : 'pink'))">
                                {{ $member->status }}
                            </x-badge>
                        </td>
                        <td class="py-2.5 text-right"><a href="{{ route('hr_office.staff.show', $member) }}" class="text-xs font-semibold text-[#1F573D] hover:underline">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-neutral-400">No staff records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
