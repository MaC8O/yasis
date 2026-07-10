<x-app-layout title="Attendance" subtitle="Read-only daily attendance history for linked child." badge="Guardian · Read-only access" role="guardian">
    <x-child-switcher :children="$children" :child="$child" route="guardian.attendance.index" />

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Present" color="green">{{ $counts['Present'] }}</x-stat-tile>
        <x-stat-tile label="Absent" color="pink">{{ $counts['Absent'] }}</x-stat-tile>
        <x-stat-tile label="Tardy" color="yellow">{{ $counts['Tardy'] }}</x-stat-tile>
        <x-stat-tile label="Attendance rate" color="blue">{{ $rate !== null ? $rate.'%' : '—' }}</x-stat-tile>
    </div>

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Date</th>
                    <th class="py-2 font-semibold">Status</th>
                    <th class="py-2 font-semibold">Section</th>
                    <th class="py-2 font-semibold">Remark</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $record->attendance_date->format('Y-m-d') }}</td>
                        <td class="py-2.5"><x-badge :color="$record->status === 'Present' ? 'green' : ($record->status === 'Absent' ? 'pink' : 'yellow')">{{ $record->status }}</x-badge></td>
                        <td class="py-2.5 text-neutral-500">{{ $record->section->name }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $record->remark ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No attendance recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
