<x-app-layout title="Leave Management" subtitle="Requests, approvals, and leave balances. HR can enter leave on behalf of non-portal staff." badge="{{ $counts['Pending'] }} pending" role="hr_office">
    <x-card title="Enter leave on behalf of staff" subtitle="For non-portal staff (receptionist, maintenance, canteen, etc.) who can't submit their own request.">
        <form method="POST" action="{{ route('hr_office.leave.submit-on-behalf') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-neutral-500 mb-1">STAFF</label>
                <select name="staff_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($staffList as $member)
                        <option value="{{ $member->id }}">{{ $member->user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-neutral-500 mb-1">TYPE</label>
                <select name="leave_type_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-neutral-500 mb-1">FROM</label>
                <input type="date" name="from_date" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-neutral-500 mb-1">TO</label>
                <input type="date" name="to_date" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Submit</button>
            <div class="sm:col-span-5">
                <input type="text" name="reason" placeholder="Reason (e.g. family event, entered by HR on behalf)" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="flex gap-3">
                @foreach (['Pending', 'Approved', 'Rejected'] as $t)
                    <a href="{{ route('hr_office.leave.index', ['tab' => $t]) }}"
                        class="px-4 py-2 rounded-lg text-sm font-semibold {{ $tab === $t ? 'bg-[#1F573D] text-white' : 'bg-white border border-neutral-200 text-neutral-600' }}">
                        {{ $t }} ({{ $counts[$t] }})
                    </a>
                @endforeach
            </div>

            <x-card>
                <div class="space-y-5">
                    @forelse ($requests as $req)
                        <div class="border-b border-neutral-100 last:border-0 pb-5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold">{{ $req->staff->user->name }}</p>
                                    <p class="text-xs text-neutral-500">{{ $req->from_date->format('d M') }} – {{ $req->to_date->format('d M') }} · {{ $req->days }} day(s) · applied {{ $req->created_at->format('d M') }}</p>
                                </div>
                                <x-badge color="blue">{{ $req->leaveType->name }} Leave</x-badge>
                            </div>
                            <p class="text-sm text-neutral-600 mt-2">
                                {{ $req->reason ?? 'No reason given' }}
                                @if ($req->submitted_by !== $req->staff_id)
                                    <span class="text-neutral-400">· entered by HR on behalf</span>
                                @endif
                            </p>
                            @if ($tab === 'Pending')
                                <div class="flex gap-3 mt-3">
                                    <form method="POST" action="{{ route('hr_office.leave.approve', $req) }}">
                                        @csrf
                                        <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('hr_office.leave.reject', $req) }}">
                                        @csrf
                                        <button type="submit" class="border border-red-300 text-red-700 font-semibold rounded-lg px-4 py-2 text-sm">Reject</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-neutral-400">No {{ strtolower($tab) }} requests.</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        <x-card title="Leave balances" subtitle="Remaining = allocated − used − pending.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Staff</th>
                        <th class="py-2 font-semibold">Annual</th>
                        <th class="py-2 font-semibold">Sick</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($balances as $staffId => $rows)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $rows->first()->staff->user->name }}</td>
                            @foreach (['Annual', 'Sick'] as $typeName)
                                @php $b = $rows->firstWhere('leaveType.name', $typeName); @endphp
                                <td class="py-2.5">{{ $b ? $b->allocated - $b->used - $b->pending : '—' }}d</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-xs text-neutral-400 mt-3">Pending requests are already reserved in these balances, so a request that would exceed the remaining figure is blocked at submission.</p>
        </x-card>
    </div>
</x-app-layout>
