@props([
    'items' => [],          // [['label' => ..., 'value' => ..., 'display' => optional], ...]
    'color' => '#2E8B57',   // validated categorical slot 1 (pine)
    'suffix' => '',
    'labelWidth' => 'w-36',
    'max' => null,
])

@php
    $items = collect($items);
    $scaleMax = max($max ?? $items->max('value') ?? 0, 1);
@endphp

<div class="space-y-2.5">
    @forelse ($items as $item)
        @php $pct = min(($item['value'] / $scaleMax) * 100, 100); @endphp
        <div class="flex items-center gap-3 group" title="{{ $item['label'] }}: {{ $item['display'] ?? number_format($item['value']).$suffix }}">
            <span class="{{ $labelWidth }} shrink-0 text-sm text-neutral-500 truncate">{{ $item['label'] }}</span>
            <div class="flex-1">
                <div class="h-4 group-hover:opacity-80 transition-opacity"
                     style="width: {{ $pct }}%; background: {{ $color }}; border-radius: 0 4px 4px 0; min-width: {{ $item['value'] > 0 ? '3px' : '0' }};"></div>
            </div>
            <span class="w-16 shrink-0 text-sm font-semibold text-right tabular-nums">{{ $item['display'] ?? number_format($item['value']).$suffix }}</span>
        </div>
    @empty
        <p class="text-sm text-neutral-400">No data yet.</p>
    @endforelse
</div>
