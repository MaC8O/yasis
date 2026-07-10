<x-app-layout title="Student Dashboard" subtitle="Quick overview of your academic progress, schedule, attendance, and notices." :badge="$student->first_name.' '.$student->last_name" role="student">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Term GPA" color="blue">{{ $termGpa ?? '—' }}</x-stat-tile>
        <x-stat-tile label="Attendance rate" color="green">{{ $attendanceRate !== null ? $attendanceRate.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="My classes" color="blue">{{ $classesCount }}</x-stat-tile>
        <x-stat-tile label="New notices">{{ $notices->count() }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Grade snapshot" subtitle="Current term weighted scores by subject.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Subject</th>
                        <th class="py-2 font-semibold">Score</th>
                        <th class="py-2 font-semibold">Letter</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($snapshot as $row)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $row->subject }}</td>
                            <td class="py-2.5">{{ $row->result['pct'] !== null ? $row->result['pct'].'%' : '—' }}</td>
                            <td class="py-2.5">{{ $row->result['letter'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-neutral-400">No grades recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <a href="{{ route('student.grades.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Open grades</a>
        </x-card>

        <x-card title="Notice board" subtitle="School notices visible to students.">
            <div class="space-y-3">
                @forelse ($notices as $notice)
                    <div class="border-b border-neutral-100 last:border-0 pb-3">
                        <p class="font-semibold text-sm">{{ $notice->title }}</p>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No notices yet.</p>
                @endforelse
            </div>
            <a href="{{ route('student.notices.index') }}" class="inline-block mt-4 text-sm font-semibold text-[#1F573D] hover:underline">Read notices</a>
        </x-card>
    </div>

    <x-card title="Attendance snapshot" subtitle="Recent records remain visible from the Student portal.">
        <div class="flex flex-wrap gap-2">
            @forelse ($recentAttendance as $record)
                <x-badge :color="$record->status === 'Present' ? 'green' : ($record->status === 'Absent' ? 'pink' : 'yellow')">
                    {{ $record->attendance_date->format('M j') }} · {{ $record->status }}
                </x-badge>
            @empty
                <p class="text-sm text-neutral-400">No attendance recorded yet.</p>
            @endforelse
        </div>
        <a href="{{ route('student.attendance.index') }}" class="inline-block mt-4 text-sm font-semibold text-[#1F573D] hover:underline">View attendance</a>
    </x-card>
</x-app-layout>
