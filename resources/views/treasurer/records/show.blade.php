@use('App\Support\Money')
@php
    $section = $student->enrollments->firstWhere('status', 'Active')?->section;
    $guardian = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
    $closing = $lines->last()?->balance ?? 0;
    $latestStatus = $lines->isEmpty() ? null : app(\App\Services\FeeSummaryService::class)->accountStatus($lines->sum('charge'), $closing);
@endphp
<x-app-layout :title="$student->name" subtitle="Statement of account" badge="Treasurer" role="treasurer">
    {{-- Account header --}}
    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 px-5 py-4">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-2 text-sm">
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Account (Student ID)</dt><dd class="font-bold text-ink tabular-nums">{{ $student->student_id_number }}</dd></div>
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Class</dt><dd class="text-ink">{{ $section?->name ?? '—' }} · {{ $student->department?->name ?? '—' }}</dd></div>
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Bill to</dt><dd class="text-ink">{{ $guardian?->user?->name ?? '—' }}</dd></div>
                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Contact</dt><dd class="text-ink">{{ $guardian?->phone ?? $guardian?->user?->phone ?? '—' }}</dd></div>
            </dl>
            <div class="md:text-right md:border-l md:border-neutral-200 md:pl-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Balance due</p>
                <p class="text-3xl font-bold tabular-nums {{ $closing > 0 ? 'text-danger' : 'text-success' }}">{{ Money::format($closing) }} <span class="text-sm font-semibold text-neutral-500">{{ Money::CURRENCY }}</span></p>
                @if ($latestStatus)<x-finance.status-pill :status="$latestStatus" class="mt-1" />@endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 px-5 py-3 border-t border-neutral-200 bg-neutral-50">
            <a href="{{ route('treasurer.reports.statement', $student) }}" target="_blank"
               class="inline-flex items-center gap-2 bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">
                <x-icon name="document" class="w-4 h-4" /> Office copy (PDF)
            </a>
            <a href="{{ route('treasurer.reports.statement', [$student, 'copy' => 'family']) }}" target="_blank"
               class="inline-flex items-center gap-2 border border-brand text-brand font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-soft transition-colors">
                <x-icon name="users" class="w-4 h-4" /> Family copy (PDF)
            </a>
            <p class="text-xs text-neutral-500 ml-1">The family copy leaves out restricted (SDA), held and unpublished lines.</p>
        </div>
    </div>

    <x-card title="Aging of balance due" subtitle="How long each unpaid charge has been open, from the date it was billed.">
        <x-finance.aging-strip :aging="$aging" />
    </x-card>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-neutral-200">
            <h2 class="text-lg font-bold text-ink">Transactions</h2>
            <p class="text-sm text-neutral-500">Office view — every imported line, with restricted and held lines flagged.</p>
        </div>
        <x-finance.statement :lines="$lines" flag-restricted />
    </div>

    <a href="{{ route('treasurer.records.index') }}" class="inline-block text-sm font-semibold text-neutral-500 hover:underline">← Back to accounts receivable</a>
</x-app-layout>
