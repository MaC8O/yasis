@php $gradesSubtitle = "Guardian read-only view of {$child->name}'s subject grades and category breakdown."; @endphp
<x-app-layout title="Grades & Reports" :subtitle="$gradesSubtitle" badge="Student view" role="guardian">
    <x-child-switcher :children="$children" :child="$child" route="guardian.grades.index" />

    <x-card>
        <form method="GET" class="flex gap-4 items-end">
            <input type="hidden" name="child" value="{{ $child->id }}">
            <div>
                <label class="block text-sm font-semibold mb-1">Term</label>
                <select name="term" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </x-card>

    @if ($report)
        @include('grades.partials.term-report', ['report' => $report, 'breakdown' => $breakdown])
    @else
        <x-card><p class="text-sm text-neutral-400">No terms are set up for this school year yet.</p></x-card>
    @endif

    @if ($comment)
        <x-card title="Homeroom comment">
            <p class="text-sm text-neutral-600">{{ $comment->comment }}</p>
        </x-card>
    @endif

    <x-card title="Report downloads" subtitle="Report cards remain read-only for guardians.">
        <div class="flex flex-wrap gap-3">
            @forelse ($releasedTerms as $released)
                <a href="{{ route('guardian.grades.report-card', ['child' => $child->id, 'term' => $released->id]) }}" target="_blank"
                    class="border border-brand text-brand font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand/5 transition-colors">
                    {{ $released->name }} report card
                </a>
            @empty
                <p class="text-sm text-neutral-400">No terms have been released for report cards yet.</p>
            @endforelse
        </div>
    </x-card>
</x-app-layout>
