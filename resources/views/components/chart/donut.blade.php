@props([
    'segments' => [],        // [['label' => ..., 'value' => ..., 'color' => ...], ...]
    'centerLabel' => null,   // small caption under the center value
    'center' => null,        // center value; defaults to the segment total
    'format' => null,        // optional callable to format legend values
])

@php
    $segments = collect($segments)->filter(fn ($s) => $s['value'] > 0)->values();
    $total = $segments->sum('value');
    $fmt = $format ?? fn ($v) => number_format($v);

    $r = 44; $c = 2 * M_PI * $r; $stroke = 16;
    $gap = $segments->count() > 1 ? 2.5 : 0; // surface gap between touching segments
    $offset = 0;
@endphp

@if ($total <= 0)
    <p class="text-sm text-neutral-400">No data yet.</p>
@else
<div class="flex items-center gap-6 flex-wrap">
    <svg viewBox="0 0 120 120" class="w-36 h-36 shrink-0" role="img" aria-label="{{ $centerLabel ?? 'Breakdown' }}">
        <g transform="rotate(-90 60 60)">
            @foreach ($segments as $segment)
                @php
                    $len = max(($segment['value'] / $total) * $c - $gap, 0.5);
                @endphp
                <circle cx="60" cy="60" r="{{ $r }}" fill="none"
                        stroke="{{ $segment['color'] }}" stroke-width="{{ $stroke }}"
                        stroke-dasharray="{{ round($len, 2) }} {{ round($c - $len, 2) }}"
                        stroke-dashoffset="{{ round(-$offset, 2) }}"
                        class="hover:opacity-80">
                    <title>{{ $segment['label'] }}: {{ $fmt($segment['value']) }} ({{ round($segment['value'] / $total * 100) }}%)</title>
                </circle>
                @php $offset += ($segment['value'] / $total) * $c; @endphp
            @endforeach
        </g>
        <text x="60" y="{{ $centerLabel ? 58 : 64 }}" text-anchor="middle" font-size="17" font-weight="700" fill="#131B1B">{{ $center ?? number_format($total) }}</text>
        @if ($centerLabel)
            <text x="60" y="72" text-anchor="middle" font-size="8.5" fill="#576661">{{ $centerLabel }}</text>
        @endif
    </svg>

    <div class="space-y-2 min-w-0">
        @foreach ($segments as $segment)
            <div class="flex items-center gap-2.5 text-sm">
                <span class="w-3 h-3 rounded shrink-0" style="background: {{ $segment['color'] }}"></span>
                <span class="text-neutral-500">{{ $segment['label'] }}</span>
                <span class="font-semibold tabular-nums ml-auto pl-4">{{ $fmt($segment['value']) }}</span>
                <span class="text-neutral-400 text-xs w-9 text-right">{{ round($segment['value'] / $total * 100) }}%</span>
            </div>
        @endforeach
    </div>
</div>
@endif
