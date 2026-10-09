@use('App\Support\Money')
@php
    $section = $student->enrollments->firstWhere('status', 'Active')?->section;
    $closing = $lines->last()?->balance ?? 0;
@endphp
<x-app-layout :title="$student->name" subtitle="Fee statement — every charge, what was paid, and what is still owed. Read-only." badge="Read-only" :role="$role">
    <div class="bg-white rounded-2xl border border-neutral-200 px-5 py-4 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4">
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Student ID</dt><dd class="font-bold text-ink tabular-nums">{{ $student->student_id_number }}</dd></div>
            <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Class</dt><dd class="text-ink">{{ $section?->name ?? '—' }}</dd></div>
            <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Department</dt><dd class="text-ink">{{ $student->department?->name ?? '—' }}</dd></div>
        </dl>
        <div class="md:text-right md:border-l md:border-neutral-200 md:pl-6">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Still owed</p>
            <p class="text-2xl font-bold tabular-nums {{ $closing > 0 ? 'text-danger' : 'text-success' }}">{{ Money::format($closing) }} <span class="text-sm font-semibold text-neutral-500">{{ Money::CURRENCY }}</span></p>
        </div>
    </div>

    <x-card title="How long the money has been owed" subtitle="Each unpaid charge, counted from the date it was charged.">
        <x-finance.aging-strip :aging="$aging" />
    </x-card>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <x-finance.statement :lines="$lines" :flag-restricted="\App\Support\FeeVisibility::leadershipSeesRestricted()" />
    </div>

    <a href="{{ route($role.'.fees.index') }}" class="inline-block text-sm font-semibold text-neutral-500 hover:underline">← Back to fee records</a>
</x-app-layout>
