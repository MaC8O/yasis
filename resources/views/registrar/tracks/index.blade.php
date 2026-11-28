<x-app-layout title="Tracks" subtitle="Each Grade 10, 11 and 12 student takes one track of subjects, science or arts, for the school year." badge="Registrar" role="registrar">
    <x-card :title="'Sections with tracks'.($activeYear ? ' · '.$activeYear->year_label : '')"
            subtitle="A student takes the compulsory subjects plus every subject of their track. The other track's subjects print as “-” on report cards and statements.">
        @if (! $activeYear)
            <p class="text-sm text-neutral-400">There is no active academic year.</p>
        @elseif ($rows->isEmpty())
            <p class="text-sm text-neutral-400">No section in the active year has tracks. Check each section's grade level, and the curriculum (set by the Admin).</p>
        @else
            <ul class="divide-y divide-neutral-100" data-track-sections>
                @foreach ($rows as $row)
                    <li class="py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('registrar.tracks.show', $row['section']) }}" class="font-semibold text-brand hover:underline">{{ $row['section']->name }}</a>
                            <p class="text-xs text-neutral-500">
                                {{ $row['section']->gradeLevel->name }} · {{ $row['students'] }} {{ Str::plural('student', $row['students']) }}
                                @foreach ($row['tracks'] as $track) · {{ \App\Models\StudentTrack::label($track) }} {{ $row['counts'][$track] ?? 0 }}@endforeach
                            </p>
                        </div>
                        @if ($row['students'] === 0)
                            <x-badge color="neutral">No students</x-badge>
                        @elseif ($row['missing'] > 0)
                            <x-badge color="yellow">{{ $row['missing'] }} without a track</x-badge>
                        @else
                            <x-badge color="green">All set</x-badge>
                        @endif
                        <a href="{{ route('registrar.tracks.show', $row['section']) }}" class="inline-flex items-center min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50">Set tracks</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
