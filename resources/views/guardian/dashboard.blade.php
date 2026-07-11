<x-app-layout title="Guardian Dashboard" subtitle="View linked child attendance, grades, imported fee status, and school notices." badge="Guardian · Read-only access" role="guardian">
    <x-child-switcher :children="$children" :child="$child" route="guardian.dashboard" />

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Attendance rate" color="blue">{{ $attendanceRate !== null ? $attendanceRate.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="Latest GPA" color="green">{{ $latestGpa ?? '—' }}</x-stat-tile>
        <x-stat-tile label="Fee status" color="yellow">{{ $feeStatus }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('guardian.attendance.index', ['child' => $child->id]) }}" class="bg-white border border-neutral-200 rounded-2xl px-5 py-4 font-semibold text-sm hover:border-[#1F573D]">Attendance</a>
        <a href="{{ route('guardian.grades.index', ['child' => $child->id]) }}" class="bg-white border border-neutral-200 rounded-2xl px-5 py-4 font-semibold text-sm hover:border-[#1F573D]">Grades & Reports</a>
        <a href="{{ route('guardian.fees.index', ['child' => $child->id]) }}" class="bg-white border border-neutral-200 rounded-2xl px-5 py-4 font-semibold text-sm hover:border-[#1F573D]">Fees</a>
        <a href="{{ route('guardian.notices.index') }}" class="bg-white border border-neutral-200 rounded-2xl px-5 py-4 font-semibold text-sm hover:border-[#1F573D]">Notices</a>
    </div>

    <x-card title="Grade snapshot" subtitle="Current term weighted scores by subject.">
        <table class="rt w-full text-sm">
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
                        <td data-label="Subject" class="py-2.5">{{ $row->subject }}</td>
                        <td data-label="Score" class="py-2.5">{{ $row->result['pct'] !== null ? $row->result['pct'].'%' : '—' }}</td>
                        <td data-label="Letter" class="py-2.5">{{ $row->result['letter'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-neutral-400">No grades recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <a href="{{ route('guardian.grades.index', ['child' => $child->id]) }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Open grades &amp; reports</a>
    </x-card>

    <x-card :title="$child->name.'’s attendance this year'" subtitle="Every recorded school day, by status.">
        <x-chart.donut :segments="$attendanceSegments" center-label="days recorded" />
    </x-card>

    <x-card title="Notify School of Absence" subtitle="Let the school know in advance when your child will be away.">
        <a href="{{ route('guardian.absence-notices.index', ['child' => $child->id]) }}" class="inline-block bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Notify Absence</a>
    </x-card>
</x-app-layout>
