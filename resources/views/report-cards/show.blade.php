{{-- One student's report card on screen, for staff, the student or a guardian. --}}
@php
    $portal ??= auth()->user()->getRoleNames()->first();
    $badge = ['student' => $card['student']->name, 'guardian' => 'Guardian · Read-only'][$portal] ?? 'Staff';
@endphp
<x-app-layout title="Report card" :subtitle="$card['student']->name.' · '.$card['section']->name.' · '.$card['term']->name" :badge="$badge" :role="$portal">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $backUrl }}" class="min-h-[44px] inline-flex items-center text-sm font-semibold text-brand hover:underline">← Back</a>
        <a href="{{ $pdfUrl }}" target="_blank" class="min-h-[44px] inline-flex items-center rounded-lg border border-brand px-4 text-sm font-semibold text-brand hover:bg-brand-soft">Download PDF (print double-sided and fold)</a>
    </div>

    @if (! empty($acknowledgeUrl))
        <x-card title="Acknowledgement">
            @if ($acknowledged)
                <p class="text-sm text-success" data-guardian-acknowledged>Acknowledged by {{ $acknowledged->user?->name }} on {{ $acknowledged->acknowledged_at->format('j M Y') }}.</p>
            @else
                <form method="POST" action="{{ $acknowledgeUrl }}">
                    @csrf
                    <p class="text-sm text-neutral-600 mb-3">Please read the report, then confirm you have seen it. Your name and the date are printed where the parent's signature goes.</p>
                    <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">I acknowledge this report</button>
                </form>
            @endif
        </x-card>
    @endif

    @include('report-cards.partials.card', ['card' => $card])
</x-app-layout>
