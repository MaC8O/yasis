@props(['color' => 'blue'])
@php
    $colors = [
        'blue' => 'bg-tint-blue text-tint-blue-ink',
        'yellow' => 'bg-tint-yellow text-tint-yellow-ink',
        'pink' => 'bg-tint-pink text-tint-pink-ink',
        'green' => 'bg-tint-green text-tint-green-ink',
        'purple' => 'bg-tint-purple text-tint-purple-ink',
        'teal' => 'bg-tint-teal text-tint-teal-ink',
        'neutral' => 'bg-neutral-200 text-neutral-700',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-medium '.($colors[$color] ?? $colors['blue'])]) }}>
    {{ $slot }}
</span>
