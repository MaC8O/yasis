@props(['status'])
@php
    // Account status as exported from the accounting system. Text always carries the meaning (§NFR a11y).
    $styles = [
        'Paid' => 'bg-success-soft text-success border-success-line',
        'Partial' => 'bg-warning-soft text-warning border-warning-line',
        'Owed' => 'bg-danger-soft text-danger border-danger-line',
        'Outstanding' => 'bg-danger-soft text-danger border-danger-line',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-semibold whitespace-nowrap '.($styles[$status] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200')]) }}>
    <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $status }}
</span>
