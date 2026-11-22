{{-- Score, stage and office visits for the active school year. Expects $summary (BehaviorStanding::summary). --}}
@if (! $summary['year'])
    <x-card>
        <p class="text-sm text-neutral-500">There is no active school year, so there is no behavior score to show yet.</p>
    </x-card>
@else
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Behavior score · {{ $summary['year']->year_label }}" :color="$summary['score'] >= 80 ? 'green' : ($summary['score'] >= 60 ? 'yellow' : 'pink')">
            <span class="tabular-nums">{{ $summary['score'] }}</span><span class="text-base font-semibold text-neutral-500"> / 100</span>
        </x-stat-tile>
        <x-stat-tile label="Stage" :color="$summary['stage'] ? 'pink' : 'green'">
            <span class="text-xl">{{ $summary['stage_label'] ?? 'No warning' }}</span>
        </x-stat-tile>
        <x-stat-tile label="Office visits this year" color="blue">
            <span class="tabular-nums">{{ $summary['office_visits'] }}</span>
        </x-stat-tile>
    </div>
    @if ($summary['open_case'])
        <div role="status" class="flex items-start gap-3 bg-warning-soft border border-warning-line text-warning text-sm rounded-xl px-4 py-3">
            <x-icon name="alert" class="w-5 h-5 mt-0.5 shrink-0" />
            <p>An expulsion review is open. Nothing has been decided: the Vice Principal and the Principal both review it. Nobody is expelled automatically.</p>
        </div>
    @endif
@endif
