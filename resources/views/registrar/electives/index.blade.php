<x-app-layout title="Electives" subtitle="Each student's choice of subject in every elective slot (Grades 11-12), for the active academic year." badge="Registrar" role="registrar">
    <x-card :title="'Sections with elective slots'.($activeYear ? ' · '.$activeYear->year_label : '')"
            subtitle="Sections are listed when their grade level's curriculum has elective slots. Each student takes one subject from each slot.">
        @if (! $activeYear)
            <p class="text-sm text-neutral-400">There is no active academic year.</p>
        @elseif ($rows->isEmpty())
            <p class="text-sm text-neutral-400">No section in the active year has elective slots. Check each section's grade level, and the curriculum (set by the Admin).</p>
        @else
            <ul class="divide-y divide-neutral-100">
                @foreach ($rows as $row)
                    <li class="py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('registrar.electives.show', $row['section']) }}" class="font-semibold text-brand hover:underline">{{ $row['section']->name }}</a>
                            <p class="text-xs text-neutral-500">{{ $row['section']->gradeLevel->name }} · {{ $row['students'] }} {{ Str::plural('student', $row['students']) }} · slots {{ $row['slots']->implode(', ') }}</p>
                        </div>
                        @if ($row['students'] === 0)
                            <x-badge color="neutral">No students</x-badge>
                        @elseif ($row['missing'] > 0)
                            <x-badge color="yellow">{{ $row['missing'] }} {{ Str::plural('choice', $row['missing']) }} missing</x-badge>
                        @else
                            <x-badge color="green">All chosen</x-badge>
                        @endif
                        <a href="{{ route('registrar.electives.show', $row['section']) }}" class="inline-flex items-center min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50">Set choices</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
