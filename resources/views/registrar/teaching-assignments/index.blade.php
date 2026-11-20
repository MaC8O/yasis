<x-app-layout title="Teacher Assignment" subtitle="Who teaches each subject in each section (active academic year). To set several subjects at once, open a section." badge="Registrar" role="registrar">
    <div x-data="{ open: false, action: '', label: '', teacher: '' }" @keydown.escape.window="open = false" class="space-y-6">
        <x-card title="Assign a teacher">
            <form method="POST" action="{{ route('registrar.teaching-assignments.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                @csrf
                <div>
                    <label for="a-section" class="block text-sm font-semibold mb-1">Section</label>
                    <select id="a-section" name="section_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="a-subject" class="block text-sm font-semibold mb-1">Subject</label>
                    <select id="a-subject" name="subject_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }} ({{ $subject->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="a-teacher" class="block text-sm font-semibold mb-1">Teacher</label>
                    <select id="a-teacher" name="teacher_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full lg:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark transition-colors">Assign</button>
            </form>
        </x-card>

        <x-card title="Current assignments" :subtitle="$assignments->count().' '.Str::plural('assignment', $assignments->count())">
            <form method="GET" class="flex gap-2 mb-4">
                <label for="ta-search" class="sr-only">Search</label>
                <input id="ta-search" type="search" name="search" value="{{ $search }}" placeholder="Section, subject or teacher"
                       class="min-w-0 flex-1 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-brand-dark">Search</button>
            </form>

            @if ($assignments->isEmpty())
                <p class="text-sm text-neutral-400">{{ $search !== '' ? 'No assignments match “'.$search.'”.' : 'No assignments yet.' }}</p>
            @else
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500 border-b border-neutral-200">
                                <th class="py-2 font-semibold">Section</th>
                                <th class="py-2 font-semibold">Subject</th>
                                <th class="py-2 font-semibold">Teacher</th>
                                <th class="py-2 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                <tr class="border-b border-neutral-100 last:border-0">
                                    <td class="py-2.5"><a href="{{ route('registrar.sections.show', $assignment->section) }}" class="text-brand hover:underline">{{ $assignment->section->name }}</a></td>
                                    <td class="py-2.5">{{ $assignment->subject->name }}</td>
                                    <td class="py-2.5">{{ $assignment->teacher->user->name }}</td>
                                    <td class="py-2.5 text-right whitespace-nowrap">
                                        <button type="button" class="text-xs font-semibold text-brand hover:underline mr-3"
                                                @click="open = true; action = @js(route('registrar.teaching-assignments.update', $assignment)); label = @js($assignment->subject->name.' — '.$assignment->section->name); teacher = '{{ $assignment->teacher_id }}'">Change teacher</button>
                                        <form method="POST" action="{{ route('registrar.teaching-assignments.destroy', $assignment) }}" class="inline"
                                              data-confirm="Remove {{ $assignment->teacher->user->name }} from {{ $assignment->subject->name }} in {{ $assignment->section->name }}? This is refused if grades are already recorded."
                                              onsubmit="return confirm(this.dataset.confirm);">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-danger hover:underline">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="md:hidden space-y-3">
                    @foreach ($assignments as $assignment)
                        <li class="rounded-xl border border-neutral-200 p-4">
                            <a href="{{ route('registrar.sections.show', $assignment->section) }}" class="font-semibold text-brand">{{ $assignment->section->name }}</a>
                            <p class="text-sm mt-1">{{ $assignment->subject->name }}</p>
                            <p class="text-sm text-neutral-500">{{ $assignment->teacher->user->name }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="min-h-[44px] px-3 rounded-lg border border-neutral-200 text-sm font-semibold text-brand"
                                        @click="open = true; action = @js(route('registrar.teaching-assignments.update', $assignment)); label = @js($assignment->subject->name.' — '.$assignment->section->name); teacher = '{{ $assignment->teacher_id }}'">Change teacher</button>
                                <form method="POST" action="{{ route('registrar.teaching-assignments.destroy', $assignment) }}"
                                      data-confirm="Remove {{ $assignment->teacher->user->name }} from {{ $assignment->subject->name }} in {{ $assignment->section->name }}? This is refused if grades are already recorded."
                                      onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="min-h-[44px] px-3 rounded-lg border border-neutral-200 text-sm font-semibold text-danger">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <a href="{{ route('registrar.sections.index') }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-neutral-500 hover:underline">← Back to sections</a>

        {{-- Change-teacher modal (bottom sheet on phones) --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="ct-title">
            <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <form method="POST" :action="action" class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-xl p-5 space-y-4">
                @csrf @method('PUT')
                <h2 id="ct-title" class="text-lg font-bold">Change teacher</h2>
                <p class="text-sm text-neutral-500" x-text="label"></p>
                <div>
                    <label for="ct-teacher" class="block text-sm font-semibold mb-1">Teacher</label>
                    <select id="ct-teacher" name="teacher_id" x-model="teacher" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
                    <button type="button" @click="open = false" class="w-full sm:w-auto border border-neutral-200 font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px]">Cancel</button>
                    <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
