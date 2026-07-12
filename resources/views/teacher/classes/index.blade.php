<x-app-layout title="My Classes" subtitle="View classes assigned by Admin or Registrar and access attendance/gradebook tools." badge="Teacher · Assigned classes only" role="teacher">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Classes" color="blue">{{ $stats['classes'] }}</x-stat-tile>
        <x-stat-tile label="Students" color="blue">{{ $stats['students'] }}</x-stat-tile>
        <x-stat-tile label="Subjects" color="green">{{ $stats['subjects'] }}</x-stat-tile>
        <x-stat-tile label="Sections" color="yellow">{{ $stats['sections'] }}</x-stat-tile>
    </div>

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Class</th>
                    <th class="py-2 font-semibold">Subject</th>
                    <th class="py-2 font-semibold">Students</th>
                    <th class="py-2 font-semibold">Department</th>
                    <th class="py-2 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5 font-semibold">{{ $row['section']->name }}</td>
                        <td class="py-2.5">{{ $row['subject'] }}</td>
                        <td class="py-2.5">{{ $row['students'] }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $row['section']->department->name }}</td>
                        <td class="py-2.5">
                            <a href="{{ route('teacher.attendance.index', ['section' => $row['section']->id]) }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Attendance</a>
                            @if ($row['gradebook'])
                                / <a href="{{ route('teacher.gradebook.index', ['section' => $row['section']->id]) }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Gradebook</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-neutral-400">No classes assigned yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
