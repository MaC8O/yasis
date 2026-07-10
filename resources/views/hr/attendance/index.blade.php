<x-app-layout title="Attendance" subtitle="Daily staff attendance — separate from student attendance. On-Leave is auto-filled from approved leave." badge="HR Office" role="hr_office">
    <x-card>
        <form method="GET" class="flex gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold mb-1">Date</label>
                <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
        </form>
    </x-card>

    <form method="POST" action="{{ route('hr_office.attendance.store') }}" id="attendance-form">
        @csrf
        <input type="hidden" name="attendance_date" value="{{ $date->toDateString() }}">

        <x-card title="Daily staff attendance" subtitle="{{ $markedCount }}/{{ $staff->count() }} marked.">
            <div class="flex justify-end gap-3 mb-4">
                <button type="button" onclick="document.querySelectorAll('input[data-present]').forEach(r => r.checked = true)"
                    class="bg-neutral-100 text-neutral-800 font-semibold rounded-lg px-4 py-2 text-sm">Mark all present</button>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Submit attendance</button>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Staff</th>
                        <th class="py-2 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staff as $member)
                        @php
                            $leave = $approvedLeave[$member->id] ?? null;
                            $current = $existing[$member->id]->status ?? ($leave ? 'On-Leave' : null);
                        @endphp
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">
                                <div class="font-semibold">{{ $member->user->name }}</div>
                                <div class="text-xs text-neutral-400">
                                    {{ $member->job_title }} · {{ $member->department?->name }}
                                    @if ($leave) · On leave (approved) @endif
                                </div>
                                <input type="hidden" name="statuses[{{ $loop->index }}][staff_id]" value="{{ $member->id }}">
                            </td>
                            <td class="py-2.5">
                                <div class="flex gap-2">
                                    @foreach (['Present', 'Absent', 'Tardy', 'On-Leave'] as $status)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="statuses[{{ $loop->parent->index }}][status]" value="{{ $status }}"
                                                class="peer sr-only" @checked($current === $status) @if($status === 'Present') data-present @endif>
                                            <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-semibold border border-neutral-200 text-neutral-600 peer-checked:bg-[#1F573D] peer-checked:text-white peer-checked:border-[#1F573D]">
                                                {{ $status }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </form>
</x-app-layout>
