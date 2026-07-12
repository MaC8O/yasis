<x-app-layout :title="$staffMember->user->name" :subtitle="$staffMember->job_title.' · '.$staffMember->staff_id_number" badge="HR Office" role="hr_office">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Profile">
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-neutral-500">Department</dt><dd class="font-semibold">{{ $staffMember->department?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Joined</dt><dd>{{ $staffMember->joined_date->format('M j, Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Phone</dt><dd>{{ $staffMember->phone ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Email</dt><dd>{{ $staffMember->user->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Portal access</dt><dd>{{ $staffMember->role_type === 'Staff' ? 'No (personnel record only)' : 'Yes — '.str_replace('_', ' ', $staffMember->role_type) }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Employment status">
            <div class="mb-4">
                <x-badge :color="$staffMember->status === 'Active' ? 'green' : ($staffMember->status === 'On Leave' ? 'blue' : ($staffMember->status === 'Probation' ? 'yellow' : 'pink'))">
                    {{ $staffMember->status }}
                </x-badge>
            </div>
            <form method="POST" action="{{ route('hr_office.staff.status', $staffMember) }}" class="flex gap-3 items-end">
                @csrf @method('PUT')
                <select name="status" class="flex-1 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach (['Active', 'On Leave', 'Probation', 'Inactive'] as $status)
                        <option value="{{ $status }}" @selected($staffMember->status === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Update</button>
            </form>
            <p class="text-xs text-neutral-400 mt-2">Offboarding sets status to Inactive — records are never deleted.</p>
        </x-card>
    </div>

    <x-card title="Leave balances (current year)">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Type</th>
                    <th class="py-2 font-semibold">Allocated</th>
                    <th class="py-2 font-semibold">Used</th>
                    <th class="py-2 font-semibold">Pending</th>
                    <th class="py-2 font-semibold">Remaining</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staffMember->leaveBalances as $balance)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $balance->leaveType->name }}</td>
                        <td class="py-2.5">{{ $balance->allocated }}</td>
                        <td class="py-2.5">{{ $balance->used }}</td>
                        <td class="py-2.5">{{ $balance->pending }}</td>
                        <td class="py-2.5 font-semibold">{{ $balance->allocated - $balance->used - $balance->pending }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-neutral-400">No leave balances set up yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <a href="{{ route('hr_office.staff.index') }}" class="inline-block text-sm font-semibold text-neutral-500 hover:underline">Back to staff records</a>
</x-app-layout>
