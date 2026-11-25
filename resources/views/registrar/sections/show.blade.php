<x-app-layout :title="$section->name" :subtitle="($section->gradeLevel?->name ?? 'Grade level not set').' · '.($section->department?->name ?? 'No department').' · '.($section->academicYear?->year_label ?? '').' · Homeroom: '.($section->homeroomTeacher?->user?->name ?? 'unassigned')" badge="Registrar" role="registrar">
    <div x-data="{ tab: @js($tab) }" class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('registrar.sections.index', ['academic_year' => $section->academic_year_id]) }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-neutral-500 hover:underline">← All sections</a>
            @if ($section->isArchived())
                <x-badge color="neutral">Archived {{ $section->archived_at->format('M j, Y') }}</x-badge>
                <form method="POST" action="{{ route('registrar.sections.restore', $section) }}" data-confirm="Restore {{ $section->name }}?" onsubmit="return confirm(this.dataset.confirm);">
                    @csrf
                    <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-brand hover:bg-neutral-50">Restore</button>
                </form>
            @else
                <form method="POST" action="{{ route('registrar.sections.archive', $section) }}"
                      data-confirm="Archive {{ $section->name }}? It will be hidden from section lists and pickers; its students, attendance and grades are kept."
                      onsubmit="return confirm(this.dataset.confirm);">
                    @csrf
                    <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-warning hover:bg-neutral-50">Archive</button>
                </form>
            @endif
            <form method="POST" action="{{ route('registrar.sections.destroy', $section) }}"
                  data-confirm="Delete {{ $section->name }} permanently?{{ $deleteBlockers ? ' It will be refused: it has '.implode(', ', $deleteBlockers).'.' : ' Its teaching assignments are removed with it.' }}"
                  onsubmit="return confirm(this.dataset.confirm);">
                @csrf @method('DELETE')
                <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-danger hover:bg-neutral-50"
                        title="{{ $deleteBlockers ? 'Has '.implode(', ', $deleteBlockers).' — archive instead' : 'No students, attendance or grades — can be deleted' }}">Delete</button>
            </form>
        </div>

        @if (session('archiveOffer') === $section->id && ! $section->isArchived())
            <div class="rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning">
                Use <span class="font-semibold">Archive</span> above instead: it hides the section from day-to-day lists and keeps all of its records.
            </div>
        @endif

        <div role="tablist" class="flex gap-2 border-b border-neutral-200 overflow-x-auto">
            <button type="button" role="tab" :aria-selected="tab === 'assignments'" @click="tab = 'assignments'"
                    :class="tab === 'assignments' ? 'border-brand text-brand' : 'border-transparent text-neutral-500'"
                    class="min-h-[44px] px-4 -mb-px border-b-2 text-sm font-semibold whitespace-nowrap">
                Teaching assignments ({{ $current->count() }})
            </button>
            <button type="button" role="tab" :aria-selected="tab === 'students'" @click="tab = 'students'"
                    :class="tab === 'students' ? 'border-brand text-brand' : 'border-transparent text-neutral-500'"
                    class="min-h-[44px] px-4 -mb-px border-b-2 text-sm font-semibold whitespace-nowrap">
                Students ({{ $roster->where('status', 'Active')->count() }})
            </button>
        </div>

        {{-- Teaching assignments: set several teachers, save once --}}
        <div x-show="tab === 'assignments'" role="tabpanel">
            <x-card title="Subject teachers" subtitle="Pick a teacher for each subject and save once. Leave a subject on “Unassigned” to clear it. Subjects with grades recorded can change teacher but can't be cleared.">
                @if ($subjects->isEmpty())
                    <p class="text-sm text-neutral-400">The {{ $section->department?->name }} subject catalogue is empty — the VP Academic adds subjects.</p>
                @else
                    <form id="bulk-form" method="POST" action="{{ route('registrar.sections.assignments.bulk', $section) }}">
                        @csrf @method('PUT')
                        <div class="divide-y divide-neutral-100">
                            @foreach ($subjects as $subject)
                                @php
                                    $assignment = $current->get($subject->id);
                                    $selected = old('teachers.'.$subject->id, $assignment?->teacher_id);
                                    $grades = $gradeCounts[$subject->id] ?? 0;
                                @endphp
                                <div class="py-3 grid grid-cols-1 md:grid-cols-12 gap-2 md:gap-4 md:items-center">
                                    <div class="md:col-span-5">
                                        <p class="font-semibold text-sm">{{ $subject->name }}</p>
                                        <p class="text-xs text-neutral-400">
                                            {{ $subject->code }}
                                            @unless ($expectedIds->contains($subject->id)) · not in this grade's expected list @endunless
                                            @if ($grades) · {{ $grades }} {{ Str::plural('grade', $grades) }} recorded @endif
                                        </p>
                                    </div>
                                    <div class="md:col-span-5">
                                        <label class="sr-only" for="t-{{ $subject->id }}">Teacher for {{ $subject->name }}</label>
                                        <select id="t-{{ $subject->id }}" name="teachers[{{ $subject->id }}]" @disabled($section->isArchived())
                                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                                            <option value="" @disabled($grades > 0 && $assignment)>{{ $grades > 0 && $assignment ? 'Unassigned (not allowed — grades exist)' : 'Unassigned' }}</option>
                                            @foreach ($teachers as $teacher)
                                                <option value="{{ $teacher->id }}" @selected((string) $selected === (string) $teacher->id)>{{ $teacher->user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-2 md:text-right">
                                        @if ($assignment && ! $section->isArchived())
                                            @if ($grades)
                                                <span class="text-xs text-neutral-400">Can't remove</span>
                                            @else
                                                <button type="submit" form="remove-{{ $assignment->id }}" class="min-h-[44px] text-sm md:text-xs font-semibold text-danger hover:underline">Remove</button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @unless ($section->isArchived())
                            <div class="sticky bottom-0 bg-white pt-4 border-t border-neutral-100 mt-2">
                                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-6 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save teacher assignments</button>
                            </div>
                        @else
                            <p class="mt-4 text-sm text-neutral-500">This section is archived. Restore it to change its teachers.</p>
                        @endunless
                    </form>

                    {{-- Remove forms live outside the bulk form (forms can't nest); buttons above point at them. --}}
                    @foreach ($current as $assignment)
                        <form id="remove-{{ $assignment->id }}" method="POST" action="{{ route('registrar.teaching-assignments.destroy', $assignment) }}" class="hidden"
                              data-confirm="Remove {{ $assignment->teacher?->user?->name }} from {{ $assignment->subject?->name }} in {{ $section->name }}?"
                              onsubmit="return confirm(this.dataset.confirm);">
                            @csrf @method('DELETE')
                        </form>
                    @endforeach
                @endif
            </x-card>
        </div>

        {{-- Students: existing roster --}}
        <div x-show="tab === 'students'" x-cloak role="tabpanel">
            <x-card title="Roster" subtitle="Students placed in this section. Place more from the Sections page.">
                @if ($roster->isEmpty())
                    <p class="text-sm text-neutral-400">No students placed in this section yet.</p>
                @else
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                                    <th class="py-2 font-semibold">Student</th>
                                    <th class="py-2 font-semibold">Student ID</th>
                                    <th class="py-2 font-semibold">Enrollment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roster as $enrollment)
                                    <tr class="border-b border-neutral-100 last:border-0">
                                        <td class="py-2.5 font-semibold">
                                            <a href="{{ route('registrar.students.index', ['selected' => $enrollment->student_id]) }}" class="text-brand hover:underline">{{ $enrollment->student?->name }}</a>
                                        </td>
                                        <td class="py-2.5">{{ $enrollment->student?->student_id_number }}</td>
                                        <td class="py-2.5"><x-badge :color="$enrollment->status === 'Active' ? 'green' : 'neutral'">{{ $enrollment->status }}</x-badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <ul class="md:hidden divide-y divide-neutral-100">
                        @foreach ($roster as $enrollment)
                            <li class="py-3 flex items-center justify-between gap-3">
                                <div>
                                    <a href="{{ route('registrar.students.index', ['selected' => $enrollment->student_id]) }}" class="font-semibold text-brand">{{ $enrollment->student?->name }}</a>
                                    <p class="text-xs text-neutral-400">{{ $enrollment->student?->student_id_number }}</p>
                                </div>
                                <x-badge :color="$enrollment->status === 'Active' ? 'green' : 'neutral'">{{ $enrollment->status }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
