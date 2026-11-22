<x-app-layout title="Behavior report" :subtitle="$report->student->name.' · '.$report->student->student_id_number" :badge="auth()->user()->hasRole('teacher') ? 'Teacher' : null">
    <div class="flex flex-wrap gap-3 text-sm">
        <a href="{{ route('behavior.index') }}" class="font-semibold text-neutral-600 hover:underline">← Back to Behavior</a>
        @if ($canOpenStudent)
            <a href="{{ route('behavior.students.show', $report->student) }}" class="font-semibold text-brand hover:underline">Open {{ $report->student->name }}’s record →</a>
        @else
            <span class="text-neutral-500">{{ $report->student->name }} is no longer in your classes, so only your own report is shown.</span>
        @endif
    </div>
    @include('behavior.partials.report-card', [
        'report' => $report,
        'isAuthor' => (int) $report->reported_by === (int) auth()->id(),
        'isVp' => auth()->user()->hasRole('vp_academic'),
    ])
</x-app-layout>
