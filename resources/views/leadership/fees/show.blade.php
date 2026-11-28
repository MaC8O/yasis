@php $section = $student->enrollments->firstWhere('status', 'Active')?->section; @endphp
<x-app-layout :title="$student->name" subtitle="Fee statements by financial year, as published to the family. Read-only." badge="Read-only" :role="$role">
    <div class="bg-white rounded-2xl border border-neutral-200 px-5 py-4">
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Student ID</dt><dd class="font-bold text-ink tabular-nums">{{ $student->student_id_number }}</dd></div>
            <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Class</dt><dd class="text-ink">{{ $section?->name ?? '—' }}</dd></div>
            <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Department</dt><dd class="text-ink">{{ $student->department?->name ?? '—' }}</dd></div>
        </dl>
    </div>

    @include('fees.partials.statements', ['statements' => $statements, 'showHidden' => $showsHidden])

    <a href="{{ route($role.'.fees.index') }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-neutral-500 hover:underline">← Back to fee records</a>
</x-app-layout>
