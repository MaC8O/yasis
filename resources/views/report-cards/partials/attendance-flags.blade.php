{{-- VP and Principal dashboards: students below the minimum attendance this term (§8). $flags from ReportCardActionsService::flagSummary(). --}}
@if ($flags && $flags['count'] > 0)
    <a href="{{ route('report-cards.attendance', ['term' => $flags['term']->id]) }}" data-attendance-flags
       class="block rounded-2xl border border-warning-line bg-warning-soft px-5 py-4 text-sm text-warning hover:shadow-md transition">
        <span class="font-semibold">{{ $flags['count'] }} {{ Str::plural('student', $flags['count']) }} below {{ $flags['minimum'] }}% attendance in {{ $flags['term']->name }}.</span>
        They may be denied permission to sit the examinations; nobody is blocked automatically. See the list →
    </a>
@endif
