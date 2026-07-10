@props([
    'points' => [],         // [['label' => ..., 'value' => ...], ...] in chronological order
    'color' => '#2E8B57',   // validated categorical slot 1 (pine)
    'suffix' => '',
    'ymax' => null,         // optional fixed scale top (e.g. 100 for percentages)
])

@php
    $points = collect($points)->values();
    $n = $points->count();

    // Nice scale top: round the max up to a clean number unless fixed.
    $rawMax = max($points->max('value') ?? 0, 1);
    $top = $ymax ?? (ceil($rawMax / 5) * 5);
    if ($top <= 0) { $top = 1; }

    $w = 640; $h = 200;
    $padL = 44; $padR = 20; $padT = 18; $padB = 28;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $x = fn ($i) => $n > 1 ? $padL + ($i / ($n - 1)) * $plotW : $padL + $plotW / 2;
    $y = fn ($v) => $padT + $plotH - (min($v, $top) / $top) * $plotH;

    $lineCoords = $points->map(fn ($p, $i) => round($x($i), 1).','.round($y($p['value']), 1))->implode(' ');
    $areaPath = 'M'.round($x(0), 1).','.($padT + $plotH)
        .' L'.$points->map(fn ($p, $i) => round($x($i), 1).','.round($y($p['value']), 1))->implode(' L')
        .' L'.round($x($n - 1), 1).','.($padT + $plotH).' Z';

    // Label every nth x tick so they never collide (~7 max).
    $every = max(1, (int) ceil($n / 7));
    $last = $points->last();
@endphp

@if ($n === 0)
    <p class="text-sm text-neutral-400">No data yet.</p>
@else
<svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-auto" role="img" aria-label="Trend chart">
    {{-- recessive hairline gridlines: 0 / mid / top --}}
    @foreach ([0, 0.5, 1] as $g)
        @php $gy = $padT + $plotH - $g * $plotH; @endphp
        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $w - $padR }}" y2="{{ $gy }}" stroke="#E1E6E1" stroke-width="1" />
        <text x="{{ $padL - 8 }}" y="{{ $gy + 3.5 }}" text-anchor="end" font-size="10" fill="#576661">{{ number_format($top * $g) }}{{ $suffix }}</text>
    @endforeach

    {{-- area wash --}}
    <path d="{{ $areaPath }}" fill="{{ $color }}" fill-opacity="0.1" />

    {{-- 2px line, round joins --}}
    <polyline points="{{ $lineCoords }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

    {{-- hover layer: one hit column per point, tooltip + marker on hover --}}
    @foreach ($points as $i => $p)
        @php $cx = round($x($i), 1); $cy = round($y($p['value']), 1); $slot = $n > 1 ? $plotW / ($n - 1) : $plotW; @endphp
        <g class="group">
            <rect x="{{ max($cx - $slot / 2, $padL) }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent">
                <title>{{ $p['label'] }}: {{ number_format($p['value'], is_float($p['value']) && fmod($p['value'], 1) !== 0.0 ? 1 : 0) }}{{ $suffix }}</title>
            </rect>
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="4" fill="{{ $color }}" stroke="#fff" stroke-width="2"
                    class="opacity-0 group-hover:opacity-100 pointer-events-none" />
        </g>
        @if ($i % $every === 0 || $i === $n - 1)
            <text x="{{ $cx }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="10" fill="#576661">{{ $p['label'] }}</text>
        @endif
    @endforeach

    {{-- end marker + direct label on the latest value --}}
    @php $ex = round($x($n - 1), 1); $ey = round($y($last['value']), 1); @endphp
    <circle cx="{{ $ex }}" cy="{{ $ey }}" r="4.5" fill="{{ $color }}" stroke="#fff" stroke-width="2" />
    <text x="{{ min($ex, $w - $padR - 2) }}" y="{{ max($ey - 10, 12) }}" text-anchor="end" font-size="12" font-weight="600" fill="#131B1B">
        {{ number_format($last['value'], is_float($last['value']) && fmod($last['value'], 1) !== 0.0 ? 1 : 0) }}{{ $suffix }}
    </text>
</svg>
@endif
