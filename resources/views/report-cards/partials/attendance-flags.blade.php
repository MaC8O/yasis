{{-- VP and Principal dashboards: students below the minimum attendance, and above the subject-absence limit (§8). $flags from ReportCardActionsService::flagSummary(). --}}
@if ($flags && ($flags['count'] > 0 || $flags['subject_count'] > 0))
    <a href="{{ route('report-cards.attendance', ['term' => $flags['term']->id]) }}" data-attendance-flags
       class="block rounded-2xl border border-warning-line bg-warning-soft px-5 py-4 text-sm text-warning hover:shadow-md transition">
        @if ($flags['count'] > 0)
            <span class="font-semibold">{{ $flags['count'] }} {{ Str::plural('student', $flags['count']) }} below {{ $flags['minimum'] }}% attendance {{ $flags['yearly'] ? 'this school year' : 'in '.$flags['term']->name }}{{ $flags['whole_period'] ? ', with too many absences to reach it' : '' }}.</span>
        @endif
        @if ($flags['subject_count'] > 0)
            <span class="font-semibold">{{ $flags['subject_count'] }} {{ Str::plural('student', $flags['subject_count']) }} more than {{ $flags['subject_limit'] }}% absent in {{ $flags['term']->name }}{{ $flags['whole_period'] ? ' (of the whole term)' : '' }}.</span>
        @endif
        They may be denied permission to sit the examinations; nobody is blocked automatically. See the list →
    </a>
@endif
