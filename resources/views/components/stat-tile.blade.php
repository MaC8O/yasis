@props(['label', 'color' => 'blue', 'hint' => null, 'href' => null])
@php
    $colors = [
        'blue' => 'bg-tint-blue',
        'yellow' => 'bg-tint-yellow',
        'pink' => 'bg-tint-pink',
        'green' => 'bg-tint-green',
        'purple' => 'bg-tint-purple',
        'teal' => 'bg-tint-teal',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
{{-- Pass href to make the tile a shortcut to the page behind the number. --}}
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'block rounded-2xl border border-black/5 px-4 py-4 sm:px-6 sm:py-5 '.($colors[$color] ?? $colors['blue'])
        .($href ? ' group transition hover:-translate-y-0.5 hover:shadow-md hover:shadow-black/5' : '')]) }}>
    <p class="text-sm text-neutral-600 flex items-center justify-between gap-2">
        {{ $label }}
        @if ($href)
            <x-icon name="chevron-right" class="w-4 h-4 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-ink" />
        @endif
    </p>
    <p class="text-xl sm:text-2xl font-bold text-ink mt-2 tabular-nums break-words">{{ $slot }}</p>
    @if ($hint)
        <p class="text-xs text-neutral-500 mt-1">{{ $hint }}</p>
    @endif
</{{ $tag }}>
