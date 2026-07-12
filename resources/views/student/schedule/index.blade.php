<x-app-layout title="My Schedule" subtitle="Subjects and teachers for your enrolled section." :badge="$student->name" role="student">
    <x-card>
        <p class="text-xs text-neutral-400 mb-4">
            A period-by-period weekly timetable isn't part of this release — this lists your assigned subjects and teachers instead.
        </p>
        <table class="rt w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Subject</th>
                    <th class="py-2 font-semibold">Section</th>
                    <th class="py-2 font-semibold">Teacher</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $a)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td data-label="Subject" class="py-2.5 font-semibold">{{ $a->subject->name }}</td>
                        <td data-label="Section" class="py-2.5">{{ $a->section->name }}</td>
                        <td data-label="Teacher" class="py-2.5 text-neutral-500">{{ $a->teacher->user->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-neutral-400">No subjects assigned yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
