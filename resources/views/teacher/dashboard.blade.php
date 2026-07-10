<x-app-layout title="Teacher Dashboard" subtitle="Manage assigned classes, attendance, grading, and announcements." badge="Teacher · Assigned classes only" role="teacher">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Assigned classes" color="blue">{{ $assignedClasses }}</x-stat-tile>
        <x-stat-tile label="Attendance pending" color="yellow">{{ $attendancePending->count() }}</x-stat-tile>
        <x-stat-tile label="Gradebook tasks" color="yellow">{{ $gradebookTasksCount }}</x-stat-tile>
        <x-stat-tile label="Announcements">{{ $announcementsCount }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="My classes" subtitle="Classes assigned by Admin or Registrar.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Class</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($todaySections as $section)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $section->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <a href="{{ route('teacher.classes.index') }}" class="inline-block mt-4 text-sm font-semibold text-[#1F573D] hover:underline">Open classes</a>
        </x-card>

        <x-card title="Action queue" subtitle="Quick tasks for today.">
            <div class="space-y-2">
                @forelse ($attendancePending as $section)
                    <x-badge color="yellow">Take attendance · {{ $section->name }}</x-badge>
                @empty
                    <x-badge color="green">All attendance marked for today</x-badge>
                @endforelse
                @if ($gradebookTasksCount > 0)
                    <x-badge color="blue">{{ $gradebookTasksCount }} gradebook(s) need category weights</x-badge>
                @endif
                @foreach ($pendingNotices as $notice)
                    <x-badge color="yellow">Absence notice to acknowledge · {{ $notice->student->first_name }} {{ $notice->student->last_name }} ({{ $notice->from_date->format('M j') }}{{ $notice->from_date->ne($notice->to_date) ? '–'.$notice->to_date->format('M j') : '' }})</x-badge>
                @endforeach
            </div>
            <a href="{{ route('teacher.attendance.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Take attendance</a>
        </x-card>
    </div>

    @if ($consecutiveAbsentees->isNotEmpty())
        <div class="bg-red-50 border border-red-200 text-red-900 text-sm rounded-xl px-5 py-3">
            <span class="font-semibold">Consecutive absences (3+ days):</span>
            {{ $consecutiveAbsentees->map(fn ($s) => $s->first_name.' '.$s->last_name)->implode(', ') }}
            — consider contacting the guardian or the Registrar's office.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Gradebook overview" subtitle="Weighted categories for assigned classes.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Class</th>
                        <th class="py-2 font-semibold">Configured</th>
                        <th class="py-2 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gradebookOverview as $row)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $row['section'] }}</td>
                            <td class="py-2.5">{{ rtrim(rtrim(number_format($row['weight'], 2), '0'), '.') }}%</td>
                            <td class="py-2.5"><x-badge :color="$row['ready'] ? 'green' : 'yellow'">{{ $row['ready'] ? 'Ready' : 'Needs weight' }}</x-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-neutral-400">No subject assignments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <a href="{{ route('teacher.gradebook.index') }}" class="inline-block mt-4 text-sm font-semibold text-[#1F573D] hover:underline">Open gradebook</a>
        </x-card>

        <x-card title="My leave balance">
            <div class="space-y-2">
                @foreach ($leaveBalances as $balance)
                    <div class="flex justify-between text-sm">
                        <span>{{ $balance->leaveType->name }}</span>
                        <span class="font-semibold">{{ $balance->allocated - $balance->used - $balance->pending }} remaining</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('teacher.leave.index') }}" class="inline-block mt-4 text-sm font-semibold text-[#1F573D] hover:underline">Request leave</a>
        </x-card>
    </div>
</x-app-layout>
