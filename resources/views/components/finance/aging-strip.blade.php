@props(['aging', 'compact' => false, 'counts' => null])
@use('App\Support\Money')
@use('App\Services\FeeSummaryService')
@php
    // Receivables aging: how long the open balance has been unpaid. Colour deepens with age.
    $tones = [
        'current' => ['bar' => 'bg-success', 'text' => 'text-success'],
        'days_31_60' => ['bar' => 'bg-gold', 'text' => 'text-warning'],
        'days_61_90' => ['bar' => 'bg-[#c2662d]', 'text' => 'text-[#9a4a1c]'],
        'over_90' => ['bar' => 'bg-danger', 'text' => 'text-danger'],
    ];
    $total = array_sum($aging);
@endphp
<div {{ $attributes }}>
    @if ($total > 0)
        <div class="flex h-2 rounded-full overflow-hidden bg-neutral-100 mb-3" aria-hidden="true">
            @foreach ($tones as $key => $tone)
                @if ($aging[$key] > 0)
                    <span class="{{ $tone['bar'] }}" @style(['width: '.$aging[$key] / $total * 100 .'%'])></span>
                @endif
            @endforeach
        </div>
    @endif
    <dl class="grid grid-cols-2 {{ $compact ? '' : 'sm:grid-cols-4' }} gap-px bg-neutral-200 rounded-lg overflow-hidden border border-neutral-200">
        @foreach (FeeSummaryService::AGING_BUCKETS as $key => $label)
            <div class="bg-white px-3 py-2">
                <dt class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                    <span class="w-2 h-2 rounded-sm {{ $tones[$key]['bar'] }}" aria-hidden="true"></span>{{ $label }}
                </dt>
                <dd class="mt-0.5 text-sm font-bold tabular-nums {{ $aging[$key] > 0 && $key !== 'current' ? $tones[$key]['text'] : 'text-ink' }}">{{ Money::format($aging[$key], true) }}</dd>
                @if ($counts !== null)
                    <dd class="text-[11px] text-neutral-500">{{ $counts[$key] }} {{ Str::plural('student', $counts[$key]) }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
</div>
