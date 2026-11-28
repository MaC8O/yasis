{{--
    A student's term on the student and guardian grade pages. $report is an
    App\Services\Results\StudentTermReport; $breakdown maps subject id => gradebook categories.
    The same report feeds the dashboards and the report-card PDF, so every page shows the same numbers.
--}}
@php
    $result = $report->result;
    $subjects = $report->subjects();
    $reasons = $report->reasons();
@endphp

<div class="rounded-xl border px-4 py-3 text-sm {{ $report->frozen ? 'border-success-line bg-success-soft text-success' : 'border-warning-line bg-warning-soft text-warning' }}">
    {{ $report->statusNote() }}
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
    <x-stat-tile :label="'Term '.($report->resultLabel() === 'GPA' ? 'GPA' : mb_strtolower($report->resultLabel()))" color="blue">{{ $report->resultDisplay() ?? '—' }}</x-stat-tile>
    <x-stat-tile label="Class of pass" color="green">{{ $result?->isComplete() ? ($result->classPending ? 'Pending ('.$result->classPending.')' : $result->classLabel) : '—' }}</x-stat-tile>
    <x-stat-tile label="Subjects" color="yellow">{{ count($subjects) }}</x-stat-tile>
</div>

@if ($reasons)
    <div class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-600" data-result-reasons>
        <p class="font-semibold">No {{ $report->resultLabel() === 'GPA' ? 'GPA' : mb_strtolower($report->resultLabel()) }} or class of pass yet</p>
        @foreach ($reasons as $reason)
            <p class="mt-1">{{ $reason }}</p>
        @endforeach
    </div>
@endif

<x-card title="Subjects" :subtitle="$report->section ? $report->section->name.($report->section->gradeLevel ? ' · '.$report->section->gradeLevel->name : '') : null">
    <div class="space-y-4">
        @forelse ($subjects as $subject)
            <div class="flex items-start justify-between gap-4 border-b border-neutral-100 last:border-0 pb-4" data-subject="{{ $subject->code }}">
                <div class="min-w-0">
                    <p class="font-semibold text-sm">{{ $subject->label }}</p>
                    @unless ($subject->inCurriculum)
                        <p class="text-xs text-neutral-400">Not in this grade's curriculum, so it has no letter and does not count.</p>
                    @endunless
                    @if (! $subject->counted && $subject->inCurriculum)
                        <p class="text-xs text-neutral-400">Not counted in the {{ $report->resultLabel() === 'GPA' ? 'GPA' : mb_strtolower($report->resultLabel()) }}.</p>
                    @endif
                    @foreach ($breakdown[$subject->subjectId] ?? [] as $cat)
                        <p class="text-xs text-neutral-500">{{ $cat['name'] }} · {{ rtrim(rtrim(number_format((float) $cat['weight'], 2), '0'), '.') }}% weight · {{ $cat['avg'] !== null ? $cat['avg'].'%' : 'not graded' }}@if (! empty($cat['detail'])) ({{ $cat['detail'] }})@endif</p>
                    @endforeach
                </div>
                <div class="text-right shrink-0">
                    @if ($subject->usesCompletion())
                        {{-- SUPW: a word, no mark (Pass, Incomplete, Complete) --}}
                        <p class="text-sm font-bold {{ $subject->bandLabel === 'INCOMPLETE' ? 'text-warning' : '' }}" data-mark>{{ $subject->bandLabel ? ucfirst(mb_strtolower($subject->bandLabel)) : '—' }}</p>
                    @else
                    <p class="text-xl font-bold" data-mark>{{ $subject->roundedMark ?? '—' }}</p>
                    @endif
                    @if ($subject->bandLabel && ! $subject->usesCompletion())
                        <x-badge :color="$subject->isFail ? 'pink' : 'green'" data-band>{{ $report->bandText($subject) }}</x-badge>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-neutral-400">No subjects on record for this term.</p>
        @endforelse
    </div>
</x-card>
