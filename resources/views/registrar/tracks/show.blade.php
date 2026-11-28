<x-app-layout :title="'Tracks · '.$section->name" :subtitle="$section->gradeLevel->name.' · '.$section->academicYear->year_label.' · one track per student'" badge="Registrar" role="registrar">
    <div class="space-y-6">
        <a href="{{ route('registrar.tracks.index') }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-neutral-500 hover:underline">← All sections with tracks</a>

        <x-card title="The tracks" subtitle="Every subject of the student's track counts, with the compulsory subjects.">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                @foreach ($tracks as $track => $subjects)
                    <div class="rounded-lg border border-neutral-200 px-4 py-3">
                        <dt class="font-semibold">{{ \App\Models\StudentTrack::label($track) }}</dt>
                        <dd class="text-neutral-500">{{ $subjects->pluck('report_label')->map(fn ($l) => Str::title(mb_strtolower($l)))->implode(', ') }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        <x-card title="Students" subtitle="Pick each student's track and save once. A track can be changed later but not cleared.">
            @if ($students->isEmpty())
                <p class="text-sm text-neutral-400">No active students in this section.</p>
            @else
                <form method="POST" action="{{ route('registrar.tracks.update', $section) }}" data-tracks-form>
                    @csrf @method('PUT')
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($students as $student)
                            @php
                                $saved = $current[$student->id] ?? null;
                                $chosen = old("tracks.{$student->id}", $saved);
                                $error = $errors->first("tracks.{$student->id}");
                            @endphp
                            <li class="py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                                <label for="t-{{ $student->id }}" class="min-w-0 flex-1">
                                    <span class="font-semibold">{{ $student->name }}</span>
                                    <span class="block text-xs text-neutral-400">{{ $student->student_id_number }}</span>
                                </label>
                                <div class="w-full sm:w-56">
                                    <select id="t-{{ $student->id }}" name="tracks[{{ $student->id }}]"
                                            class="w-full rounded-lg border {{ $error ? 'border-danger' : 'border-neutral-200' }} bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                                        @unless ($saved)
                                            <option value="">No track yet</option>
                                        @endunless
                                        @foreach ($tracks->keys() as $track)
                                            <option value="{{ $track }}" @selected($chosen === $track)>{{ \App\Models\StudentTrack::label($track) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($error)
                                        <p class="mt-1 text-xs text-danger">{{ $error }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <button type="submit" class="mt-4 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save tracks</button>
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
