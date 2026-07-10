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

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Term GPA" color="blue">{{ $termGpa ?? '—' }}</x-stat-tile>
        <x-stat-tile label="Overall score" color="green">{{ $overallScore !== null ? $overallScore.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="Subjects" color="yellow">{{ $subjects->count() }}</x-stat-tile>
    </div>

    <x-card title="Subject gradebook breakdown">
        <div class="space-y-4">
            @forelse ($subjects as $row)
                <div class="flex items-center justify-between border-b border-neutral-100 last:border-0 pb-4">
                    <div>
                        <p class="font-semibold text-sm">{{ $row->subject }}</p>
                        @foreach ($row->result['breakdown'] as $cat)
                            <p class="text-xs text-neutral-500">{{ $cat['name'] }} — {{ rtrim(rtrim(number_format($cat['weight'], 2), '0'), '.') }}% weight, {{ $cat['avg'] !== null ? round($cat['avg']).'%' : 'not graded' }}</p>
                        @endforeach
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold">{{ $row->result['pct'] !== null ? $row->result['pct'].'%' : '—' }}</p>
                        @if ($row->result['letter'])
                            <x-badge color="green">{{ $row->result['letter'] }}</x-badge>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No subjects on record for this term.</p>
            @endforelse
        </div>
    </x-card>

    @if ($comment)
        <x-card title="Homeroom comment">
            <p class="text-sm text-neutral-600">{{ $comment->comment }}</p>
        </x-card>
    @endif

    <x-card title="Report downloads" subtitle="Report cards remain read-only for guardians.">
        <div class="flex flex-wrap gap-3">
            @forelse ($releasedTerms as $released)
                <a href="{{ route('guardian.grades.report-card', ['child' => $child->id, 'term' => $released->id]) }}" target="_blank"
                    class="border border-[#1F573D] text-[#1F573D] font-semibold rounded-lg px-4 py-2 text-sm">
                    {{ $released->name }} report card
                </a>
            @empty
                <p class="text-sm text-neutral-400">No terms have been released for report cards yet.</p>
            @endforelse
        </div>
    </x-card>
</x-app-layout>
