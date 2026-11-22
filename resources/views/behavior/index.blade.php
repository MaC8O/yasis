@php
    $user = auth()->user();
    $isVp = $user->hasRole('vp_academic');
    $isTeacher = $user->hasRole('teacher');
    $badge = match (true) { $isVp => 'VP', $user->hasRole('principal') => 'Principal', $user->hasRole('admin') => 'Admin', $user->hasRole('registrar') => 'Registrar', default => 'Teacher' };
    $subtitle = match (true) {
        $isVp => 'Review reports, deduct or restore points, and follow students at each warning stage.',
        $isTeacher => 'Students you teach this school year. Report an incident from a student’s page.',
        default => 'Behavior records for all students. Read only.',
    };
    $stageLabels = \App\Models\BehaviorFlag::LABELS;
@endphp
<x-app-layout title="Behavior" :subtitle="$subtitle" :badge="$badge">
    @if (! $year)
        <x-card><p class="text-sm text-neutral-500">There is no active school year. Behavior scores start once the Admin activates one.</p></x-card>
    @endif

    @if ($year && ($isVp || ! $isTeacher))
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @if ($isVp)
                <x-stat-tile label="Awaiting review" color="yellow">{{ $queue->count() }}</x-stat-tile>
            @endif
            @foreach ($stageLabels as $stage => $label)
                <x-stat-tile :label="$label" :color="$stage === 'Warning' ? 'yellow' : 'pink'" :href="route('behavior.index', ['stage' => $stage])">{{ $openFlagCounts[$stage] ?? 0 }}</x-stat-tile>
            @endforeach
            @if (! $isVp)
                <x-stat-tile label="Open expulsion reviews" color="purple" :href="route('behavior.cases.index')">{{ $openCases->count() }}</x-stat-tile>
            @endif
        </div>
    @endif

    @if ($isVp)
        <x-card title="Awaiting review" subtitle="Oldest first. Deduct points or dismiss with a reason — both are shown to the student and guardians.">
            @forelse ($queue as $report)
                <div class="{{ $loop->first ? '' : 'mt-3' }}">
                    @include('behavior.partials.report-card', ['report' => $report, 'isVp' => true, 'showStudent' => true])
                    <a href="{{ route('behavior.students.show', $report->student_id) }}" class="inline-block mt-1 text-xs font-semibold text-brand hover:underline">Open {{ $report->student?->name }}’s record →</a>
                </div>
            @empty
                <p class="text-sm text-neutral-400">Nothing is waiting for review.</p>
            @endforelse
        </x-card>

        @if ($openCases->isNotEmpty())
            <x-card title="Open expulsion reviews">
                <ul class="divide-y divide-neutral-100">
                    @foreach ($openCases as $case)
                        <li class="py-2 flex items-center justify-between gap-3 text-sm">
                            <a href="{{ route('behavior.cases.show', $case) }}" class="font-semibold text-brand hover:underline">{{ $case->student?->name }}</a>
                            <span class="text-neutral-500">Round {{ $case->round }} · waiting for the {{ $case->awaiting }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endif

    @if ($isTeacher)
        <x-card title="My reports" subtitle="Reports you wrote, including students you no longer teach. You can edit or withdraw a report until the Vice Principal reviews it.">
            @forelse ($myReports as $report)
                <a href="{{ route('behavior.reports.show', $report) }}" class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-neutral-100 last:border-0 text-sm hover:bg-neutral-50 -mx-2 px-2 rounded-lg">
                    <span><span class="font-semibold">{{ $report->student?->name }}</span> · {{ $report->category?->name }} · {{ $report->incident_date->format('j M Y') }}</span>
                    <x-badge :color="$report->statusColor()">{{ $report->statusLabel() }}</x-badge>
                </a>
            @empty
                <p class="text-sm text-neutral-400">You have not written any behavior reports yet.</p>
            @endforelse
        </x-card>
    @endif

    <x-card :title="$isTeacher ? 'My students' : 'Students'">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-4">
            <label class="sr-only" for="behavior-search">Search students</label>
            <input id="behavior-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name or student ID"
                   class="flex-1 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            <label class="sr-only" for="behavior-stage">Stage</label>
            <select id="behavior-stage" name="stage" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                <option value="">Any stage</option>
                @foreach ($stageLabels as $stage => $label)
                    <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Filter</button>
        </form>

        @if ($students->isEmpty())
            <p class="text-sm text-neutral-400">{{ $isTeacher ? 'No students in your classes this school year match.' : 'No students match.' }}</p>
        @else
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Student</th>
                            <th class="py-2 font-semibold">Department</th>
                            <th class="py-2 font-semibold text-right">Score</th>
                            <th class="py-2 font-semibold pl-6">Stage</th>
                            <th class="py-2 font-semibold text-right">Office visits</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php $s = $summaries[$student->id] ?? null; @endphp
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5"><a href="{{ route('behavior.students.show', $student) }}" class="font-semibold text-brand hover:underline">{{ $student->name }}</a>
                                    <span class="block text-xs text-neutral-400">{{ $student->student_id_number }}</span></td>
                                <td class="py-2.5 text-neutral-600">{{ $student->department?->name }}</td>
                                <td class="py-2.5 text-right font-semibold tabular-nums">{{ $s['score'] ?? '—' }}</td>
                                <td class="py-2.5 pl-6">@if ($s && $s['stage'])<x-badge :color="$s['stage'] === 'Warning' ? 'yellow' : 'pink'">{{ $s['stage_label'] }}</x-badge>@else<span class="text-neutral-400">—</span>@endif</td>
                                <td class="py-2.5 text-right tabular-nums">{{ $s['office_visits'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="md:hidden space-y-3">
                @foreach ($students as $student)
                    @php $s = $summaries[$student->id] ?? null; @endphp
                    <li>
                        <a href="{{ route('behavior.students.show', $student) }}" class="block border border-neutral-200 rounded-xl p-4 active:bg-neutral-50">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-brand">{{ $student->name }}</p>
                                    <p class="text-xs text-neutral-400">{{ $student->student_id_number }} · {{ $student->department?->name }}</p>
                                </div>
                                @if ($s && $s['stage'])<x-badge :color="$s['stage'] === 'Warning' ? 'yellow' : 'pink'">{{ $s['stage_label'] }}</x-badge>@endif
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                <div><dt class="text-xs text-neutral-500">Score</dt><dd class="font-semibold tabular-nums">{{ $s['score'] ?? '—' }}</dd></div>
                                <div><dt class="text-xs text-neutral-500">Office visits</dt><dd class="font-semibold tabular-nums">{{ $s['office_visits'] ?? 0 }}</dd></div>
                            </dl>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 text-xs">{{ $students->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
