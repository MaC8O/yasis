<x-app-layout title="HR Dashboard" subtitle="Staff overview — headcount, attendance, and leave at a glance." :badge="'Academic Year '.($activeYear?->year_label ?? '—')" role="hr_office">
    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
        <x-stat-tile label="Total staff" color="blue">{{ $totalStaff }}</x-stat-tile>
        <x-stat-tile label="Active today" color="green">{{ $activeToday }}</x-stat-tile>
        <x-stat-tile label="On leave today" color="yellow">{{ $onLeaveToday }}</x-stat-tile>
        <x-stat-tile label="Attendance rate" color="green">{{ $attendanceRate }}%</x-stat-tile>
        <x-stat-tile label="Pending leave requests" color="pink">{{ $pendingCount }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Headcount by department" subtitle="Across teaching and non-teaching roles.">
            @php $maxCount = $headcountByDepartment->max('staff_profiles_count') ?: 1; @endphp
            <div class="space-y-3">
                @forelse ($headcountByDepartment as $department)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>{{ $department->name }}</span>
                            <span class="font-semibold">{{ $department->staff_profiles_count }}</span>
                        </div>
                        <div class="h-3 rounded-full bg-neutral-100 overflow-hidden">
                            <div class="h-full rounded-full bg-[#1F573D]" style="width: {{ $department->staff_profiles_count / $maxCount * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No staff assigned to a department yet.</p>
                @endforelse
            </div>
            <a href="{{ route('hr_office.staff.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Open staff records</a>
        </x-card>

        <x-card title="Pending leave requests" subtitle="Awaiting your decision.">
            <div class="space-y-3">
                @forelse ($pendingRequests as $req)
                    <div class="flex items-center justify-between border-b border-neutral-100 last:border-0 pb-3">
                        <div>
                            <p class="font-semibold text-sm">{{ $req->staff->user->name }}</p>
                            <p class="text-xs text-neutral-500">{{ $req->leaveType->name }} Leave · {{ $req->from_date->format('d M') }}–{{ $req->to_date->format('d M') }} ({{ $req->days }}d)</p>
                        </div>
                        <x-badge color="yellow">Pending</x-badge>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No pending requests.</p>
                @endforelse
            </div>
            <a href="{{ route('hr_office.leave.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Review leave requests</a>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-card title="Add staff record">
            <p class="text-sm text-neutral-500">Onboard a new employee — teaching or non-teaching — with role, department, and start date.</p>
            <a href="{{ route('hr_office.staff.create') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add new staff</a>
        </x-card>

        <x-card title="Today's attendance">
            <p class="text-sm text-neutral-500">{{ $markedToday }}/{{ $totalStaff }} staff marked for today.</p>
            <div class="h-3 rounded-full bg-neutral-100 overflow-hidden mt-3">
                <div class="h-full rounded-full bg-[#1F573D]" style="width: {{ $totalStaff > 0 ? $markedToday / $totalStaff * 100 : 0 }}%"></div>
            </div>
            <a href="{{ route('hr_office.attendance.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Mark attendance</a>
        </x-card>

        <x-card title="Scope note">
            <p class="text-sm text-neutral-500">
                Initial HR phase covers Staff Records, Attendance, and Leave Management per the school's request.
                Payroll and further HR features are planned for a later iteration.
            </p>
        </x-card>
    </div>
</x-app-layout>
