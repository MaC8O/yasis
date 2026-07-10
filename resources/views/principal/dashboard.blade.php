<x-app-layout title="Principal Dashboard" subtitle="Whole-school oversight — enrollment, attendance, academics, staff, finance, and two-key approvals." :badge="'Academic Year '.($activeYear?->year_label ?? '—')" role="principal">
    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
        <x-stat-tile label="Total enrollment" color="blue">{{ $totalEnrollment }}</x-stat-tile>
        <x-stat-tile label="Attendance rate" color="green">{{ $attendanceRate !== null ? $attendanceRate.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="Academic average" color="green">{{ $academicAverage !== null ? $academicAverage.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="Fee collection" color="yellow">{{ $feeCollectionRate !== null ? $feeCollectionRate.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="Pending approvals" color="pink">{{ $pendingApprovals }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Enrollment by department" subtitle="Board-ready student numbers — {{ $totalEnrollment }} enrolled in total.">
            <x-chart.bar-list :items="$enrollmentByDepartment->map(fn ($d) => ['label' => $d->name, 'value' => $d->students_count])" />
            <a href="{{ route('principal.board-reports.index') }}" class="inline-block mt-5 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Open Board reports</a>
        </x-card>

        <x-card title="Attendance — recent school days" subtitle="School-wide share of students present, tardy, or excused.">
            <x-chart.trend :points="$attendanceTrend" suffix="%" :ymax="100" />
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Fee collection status" subtitle="Imported fee records by account status (read-only).">
            <x-chart.donut :segments="$feeStatusSegments" center-label="total billed"
                :center="number_format(collect($feeStatusSegments)->sum('value') / 1000000, 1).'M'"
                :format="fn ($v) => number_format($v / 1000000, 1).'M'" />
        </x-card>

        <x-card title="Approval queue" subtitle="Two-key items — VP has signed; awaiting you.">
            <p class="text-sm text-neutral-500">{{ $pendingApprovals }} item(s) awaiting your co-approval.</p>
            <a href="{{ route('principal.approvals.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Review all approvals</a>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-card title="Assist registration">
            <p class="text-sm text-neutral-500">Step in during admissions peaks — create a student record alongside the Registrar.</p>
            <a href="{{ route('principal.registration.create') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Assist registration</a>
        </x-card>

        <x-card title="Finance oversight">
            <x-badge color="blue">Read-only</x-badge>
            <p class="text-sm text-neutral-500 mt-3">Outstanding balance</p>
            <p class="text-xl font-bold text-[#C9A227]">{{ number_format($outstandingTotal) }}</p>
            <p class="text-sm text-neutral-500 mt-2">Collected this term <span class="font-semibold text-green-700">{{ $feeCollectionRate !== null ? $feeCollectionRate.'%' : '—' }}</span></p>
            <a href="{{ route('principal.fees.index') }}" class="inline-block mt-4 border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-5 py-2.5 text-sm">View fee records</a>
        </x-card>

        <x-card title="Communication">
            <p class="text-sm text-neutral-500">Publish school-wide or targeted notices to staff, guardians, students, or selected departments.</p>
            <a href="{{ route('principal.announcements.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Create announcement</a>
        </x-card>
    </div>

    <x-card title="Staff / HR overview" subtitle="HR Office owns all staff data entry and leave decisions; surfaced here because HR reports to the Principal.">
        <x-badge color="green">Read-only</x-badge>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            <div>
                <p class="text-xs text-neutral-500 uppercase">Staff headcount</p>
                <p class="text-2xl font-bold">{{ $staffHeadcount }}</p>
                <p class="text-xs text-neutral-400">teaching + non-teaching</p>
            </div>
            <div>
                <p class="text-xs text-neutral-500 uppercase">On leave today</p>
                <p class="text-2xl font-bold">{{ $onLeaveToday }}</p>
                <p class="text-xs text-neutral-400">auto from approved leave</p>
            </div>
            <div>
                <p class="text-xs text-neutral-500 uppercase">Pending leave</p>
                <p class="text-2xl font-bold">{{ $pendingLeave }}</p>
                <p class="text-xs text-neutral-400">awaiting HR decision</p>
            </div>
        </div>
    </x-card>
</x-app-layout>
