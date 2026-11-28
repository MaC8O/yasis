<x-app-layout title="SUPW" subtitle="Pass, Incomplete or Complete for each student. SUPW has no mark and counts in no average or GPA." badge="Teacher · Assigned classes only" role="teacher">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div>
                <label for="gb-assignment" class="block text-sm font-semibold mb-1">Class / Subject</label>
                <select id="gb-assignment" name="assignment" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                    @foreach ($assignments as $a)
                        <option value="{{ $a->id }}" @selected($a->section_id === $section->id && $a->subject_id === $subject->id)>{{ $a->section->name }} — {{ $a->subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="gb-term" class="block text-sm font-semibold mb-1">Term</label>
                <select id="gb-term" name="term" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                    @foreach ($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </x-card>

    @if (! $term)
        <x-card><p class="text-sm text-neutral-400">There is no active school year.</p></x-card>
    @else
        @if ($term->is_locked)
            <div class="bg-warning-soft border border-warning-line text-warning text-sm rounded-xl px-5 py-3" data-supw-locked>
                <span class="font-semibold">{{ $term->name }} is locked.</span>
                Only Incomplete can still change, to Complete, when the student finishes the work. The report card and, for the last term, the Statement of Marks show it straight away.
            </div>
        @endif

        <x-card :title="$offering->report_label.' · '.$section->name.' · '.$term->name" subtitle="Complete means work that was Incomplete has been finished. It counts as Pass. There is no Fail.">
            @if ($roster->isEmpty())
                <p class="text-sm text-neutral-400">No active students in this class.</p>
            @else
                <form method="POST" action="{{ route('teacher.gradebook.words.save') }}" data-supw-form>
                    @csrf @method('PUT')
                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                    <input type="hidden" name="term_id" value="{{ $term->id }}">
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($roster as $student)
                            @php $current = $words[$student->id] ?? null; $locked = $term->is_locked && $current !== 'Incomplete'; @endphp
                            <li class="py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4" data-supw-row="{{ $student->id }}">
                                <label for="w-{{ $student->id }}" class="min-w-0 flex-1">
                                    <span class="font-semibold">{{ $student->name }}</span>
                                    <span class="block text-xs text-neutral-400">{{ $student->student_id_number }}</span>
                                </label>
                                <select id="w-{{ $student->id }}" name="words[{{ $student->id }}]" @disabled($locked)
                                        class="w-full sm:w-48 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px] disabled:opacity-60">
                                    <option value="">—</option>
                                    @foreach (\App\Models\SubjectStatus::STATUSES as $word)
                                        @if (! $term->is_locked || $word === $current || ($current === 'Incomplete' && $word === 'Complete'))
                                            <option value="{{ $word }}" @selected($current === $word)>{{ $word }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </li>
                        @endforeach
                    </ul>
                    @if (! $term->is_locked || in_array('Incomplete', $words, true))
                        <button type="submit" class="mt-4 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save</button>
                    @endif
                </form>
            @endif
        </x-card>
    @endif
</x-app-layout>
