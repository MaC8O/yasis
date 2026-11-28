{{-- One flagged student's figures (§8). $row from ReportCardActionsService; $period: "year" or "term". --}}
@if ($row['period_days'] !== null)
    {{ $row['absences'] }} {{ Str::plural('absence', $row['absences']) }} of {{ $row['period_days'] }} school days in the {{ $period }} (limit {{ $row['limit'] }})<span class="text-neutral-500"> · {{ $row['pct'] }}% attended so far{{ $row['late_absences'] ? ', '.$row['late_absences'].' from late arrivals' : '' }}</span>
@else
    {{ $row['pct'] }}% attended <span class="text-neutral-500">({{ $row['attended'] }} of {{ $row['counted'] }} days so far{{ $row['late_absences'] ? ', '.$row['late_absences'].' from late arrivals' : '' }})</span>
@endif
