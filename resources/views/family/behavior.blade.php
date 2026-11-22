{{-- Student and guardian behavior page. Read only; everything is shown, including withdrawn reports. --}}
<x-app-layout title="Behavior" :subtitle="$role === 'guardian' ? $student->name.'’s behavior record' : 'Your behavior record'" :role="$role">
    @if ($children && $children->count() > 1)
        <x-child-switcher :children="$children" :child="$student" route="guardian.behavior.index" />
    @endif

    @include('behavior.partials.summary', ['summary' => $summary])

    <x-card title="How the score works">
        <p class="text-sm text-neutral-600">Every student starts each school year at 100. A teacher’s report alone does not change the score: the Vice Principal reviews each report and either makes no deduction or deducts points, always with a written reason. Points can be restored if a deduction was a mistake. Office visits count the times a teacher sent {{ $role === 'guardian' ? 'your child' : 'you' }} to the office this year.</p>
    </x-card>

    <x-card title="Reports" :subtitle="$reports->isEmpty() ? null : 'Newest first. Nothing is removed: withdrawn reports and earlier versions of edited ones stay visible.'">
        <div class="space-y-3">
            @forelse ($reports as $report)
                @include('behavior.partials.report-card', ['report' => $report])
            @empty
                <p class="text-sm text-neutral-400">No behavior reports.</p>
            @endforelse
        </div>
    </x-card>

    @if ($entries->isNotEmpty())
        <x-card title="Score history">
            <ul class="divide-y divide-neutral-100 text-sm">
                @foreach ($entries as $entry)
                    <li class="py-2.5">
                        <p class="flex justify-between gap-2"><span class="font-semibold">{{ $entry->type }} · {{ $entry->created_at->format('j M Y') }}</span>
                            <span class="font-semibold tabular-nums {{ $entry->isDeduction() ? 'text-danger' : 'text-success' }}">{{ $entry->isDeduction() ? '−' : '+' }}{{ $entry->points_applied }}</span></p>
                        <p class="text-neutral-600">{{ $entry->reason }} <span class="text-xs text-neutral-400">· score {{ $entry->score_before }} → {{ $entry->score_after }}</span></p>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-app-layout>
