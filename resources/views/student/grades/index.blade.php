<x-app-layout title="My Grades" subtitle="Subject grades and category breakdown." :badge="$student->name" role="student">
    <x-card>
        <form method="GET" class="flex gap-4 items-end">
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

    <x-card title="Report card downloads">
        <div class="flex flex-wrap gap-3">
            @forelse ($releasedTerms as $released)
                <a href="{{ route('student.grades.report-card', ['term' => $released->id]) }}" target="_blank"
                    class="border border-brand text-brand font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand/5 transition-colors">
                    {{ $released->name }} report card
                </a>
            @empty
                <p class="text-sm text-neutral-400">No terms have been released for report cards yet.</p>
            @endforelse
        </div>
    </x-card>
</x-app-layout>
