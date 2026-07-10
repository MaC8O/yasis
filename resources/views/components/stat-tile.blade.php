@props(['label', 'color' => 'blue'])
@php
    $colors = [
        'blue' => 'bg-[#DCE7F9]',
        'yellow' => 'bg-[#F5E4A8]',
        'pink' => 'bg-[#F6D9D9]',
        'green' => 'bg-[#D7ECD9]',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-neutral-200 px-6 py-5 '.($colors[$color] ?? $colors['blue'])]) }}>
    <p class="text-sm text-neutral-600">{{ $label }}</p>
    <p class="text-2xl font-bold mt-2">{{ $slot }}</p>
</div>
