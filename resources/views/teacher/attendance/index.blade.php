<x-app-layout title="Class Attendance" subtitle="Collect and edit attendance for classes assigned to the teacher." badge="Teacher · Assigned classes only" role="teacher">
    @if ($pendingAcknowledgement->isNotEmpty())
        <x-card title="Absence notices" subtitle="Notifications from guardians for your homeroom. Acknowledge below — Excused is applied when you take attendance.">
            <div class="space-y-3">
                @foreach ($pendingAcknowledgement as $notice)
                    <div class="flex items-center justify-between bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-3">
                        <div>
                            <p class="font-semibold text-sm">{{ $notice->student->name }}</p>
                            <p class="text-xs text-neutral-500">{{ $notice->from_date->format('M j') }} – {{ $notice->to_date->format('M j') }} · {{ $notice->reason ?? 'No reason given' }}</p>
                        </div>
                        <form method="POST" action="{{ route('teacher.attendance.acknowledge', $notice) }}">
                            @csrf
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Acknowledge</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold mb-1">Class</label>
                <select name="section" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($sections as $s)
                        <option value="{{ $s->id }}" @selected($s->id === $section->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Date</label>
                <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Present" color="green">{{ $counts['Present'] }}</x-stat-tile>
        <x-stat-tile label="Absent" color="pink">{{ $counts['Absent'] }}</x-stat-tile>
        <x-stat-tile label="Tardy" color="yellow">{{ $counts['Tardy'] }}</x-stat-tile>
        <x-stat-tile label="Excused" color="blue">{{ $counts['Excused'] }}</x-stat-tile>
    </div>

    <form method="POST" action="{{ route('teacher.attendance.store') }}">
        @csrf
        <input type="hidden" name="section_id" value="{{ $section->id }}">
        <input type="hidden" name="attendance_date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="term_id" value="{{ $term?->id }}">

        <x-card title="Attendance sheet: {{ $section->name }}" subtitle="Mark present, absent, tardy, add remarks, and save.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Student ID</th>
                        <th class="py-2 font-semibold">Name</th>
                        <th class="py-2 font-semibold text-center">Present</th>
                        <th class="py-2 font-semibold text-center">Absent</th>
                        <th class="py-2 font-semibold text-center">Tardy</th>
                        <th class="py-2 font-semibold text-center">Excused</th>
                        <th class="py-2 font-semibold">Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roster as $student)
                        @php
                            $currentStatus = $existing->get($student->id)?->status ?? ($activeNotices->has($student->id) ? 'Excused' : 'Present');
                            $currentRemark = $existing->get($student->id)?->remark ?? ($activeNotices->has($student->id) ? 'Excused notice on file' : '');
                        @endphp
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $student->student_id_number }}</td>
                            <td class="py-2.5 font-semibold">{{ $student->name }}</td>
                            <input type="hidden" name="statuses[{{ $loop->index }}][student_id]" value="{{ $student->id }}">
                            @foreach (['Present', 'Absent', 'Tardy', 'Excused'] as $status)
                                <td class="py-2.5 text-center">
                                    <input type="radio" name="statuses[{{ $loop->parent->index }}][status]" value="{{ $status }}" @checked($currentStatus === $status)>
                                </td>
                            @endforeach
                            <td class="py-2.5">
                                <input type="text" name="statuses[{{ $loop->index }}][remark]" value="{{ $currentRemark }}" placeholder="—"
                                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <button type="submit" class="mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Save Attendance</button>
            <p class="text-xs text-neutral-400 mt-2">Every save is written to the audit log for non-repudiation.</p>
        </x-card>
    </form>
</x-app-layout>
