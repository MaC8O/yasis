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

    <x-card title="Notify School of Absence" subtitle="Let the school know in advance when your child will be away.">
        <a href="{{ route('guardian.absence-notices.index', ['child' => $child->id]) }}" class="inline-block bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Notify Absence</a>
    </x-card>
</x-app-layout>
