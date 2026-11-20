@php
    $sectionData = $sections->mapWithKeys(fn ($s) => [$s->id => [
        'id' => $s->id,
        'name' => $s->name,
        'department_id' => $s->department_id,
        'homeroom_teacher_id' => $s->homeroom_teacher_id,
        'capacity' => $s->capacity,
        'action' => route('registrar.sections.update', $s),
    ]]);
    // Re-open the drawer with the submitted values after a validation error.
    $reopen = old('_form');
@endphp
<x-app-layout title="Class Sections" subtitle="Create, edit and archive sections, assign homeroom and subject teachers, and place students — Registrar-owned." badge="Registrar" role="registrar">
    <div x-data="sectionDrawer(@js($sectionData), @js($reopen), @js(old()))" @keydown.escape.window="close()" class="space-y-6">

        @if (session('archiveOffer') && ($offer = \App\Models\Section::find(session('archiveOffer'))) && ! $offer->isArchived())
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning">
                <p class="flex-1">Archive <span class="font-semibold">{{ $offer->name }}</span> instead? It disappears from day-to-day lists, and its students, attendance and grades are kept. You can restore it later.</p>
                <form method="POST" action="{{ route('registrar.sections.archive', $offer) }}"
                      data-confirm="Archive {{ $offer->name }}? It will be hidden from section lists and pickers; all of its records are kept."
                      onsubmit="return confirm(this.dataset.confirm);">
                    @csrf
                    <button type="submit" class="w-full sm:w-auto bg-white border border-warning-line text-warning font-semibold rounded-lg px-4 py-2 min-h-[44px] hover:bg-warning-soft">Archive section</button>
                </form>
            </div>
        @endif

        <x-card>
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                <div>
                    <label for="f-year" class="block text-sm font-semibold mb-1">Academic year</label>
                    <select id="f-year" name="academic_year" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($year->id == $yearId)>{{ $year->year_label }}{{ $year->is_active ? ' (active)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-dept" class="block text-sm font-semibold mb-1">Department</label>
                    <select id="f-dept" name="department" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($filters['department'] == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-2">
                    <label for="f-search" class="block text-sm font-semibold mb-1">Search</label>
                    <div class="flex gap-2">
                        <input id="f-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Section or teacher name"
                               class="min-w-0 flex-1 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-brand-dark transition-colors">Search</button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                    <label class="inline-flex items-center gap-2 text-sm min-h-[44px]">
                        <input type="checkbox" name="archived" value="1" @checked($filters['archived']) onchange="this.form.submit()" class="rounded border-neutral-300 text-brand">
                        Show archived
                    </label>
                </div>
            </form>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" @click="openCreate()" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark transition-colors">+ New section</button>
                <a href="{{ route('registrar.teaching-assignments.index') }}" class="inline-flex items-center border border-neutral-200 font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-neutral-50">All teaching assignments</a>
            </div>
        </x-card>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-tile label="Sections" color="blue">{{ $stats['sections'] }}</x-stat-tile>
            <x-stat-tile label="With homeroom teacher" color="blue">{{ $stats['assignedTeachers'] }}</x-stat-tile>
            <x-stat-tile label="Students placed" color="green">{{ $stats['studentsPlaced'] }}</x-stat-tile>
            <x-stat-tile label="Open seats" color="yellow">{{ $stats['openSeats'] }}</x-stat-tile>
        </div>

        @forelse ($grouped as $departmentName => $group)
            <x-card :title="$departmentName" :subtitle="$group->count().' '.Str::plural('section', $group->count())">
                {{-- Desktop / tablet table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500 border-b border-neutral-200">
                                <th class="py-2 font-semibold">Section</th>
                                <th class="py-2 font-semibold">Homeroom teacher</th>
                                <th class="py-2 font-semibold">Students</th>
                                <th class="py-2 font-semibold">Subjects with a teacher</th>
                                <th class="py-2 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group as $section)
                                @php $cov = $coverage[$section->id]; $students = $section->enrollments->where('status', 'Active')->count(); @endphp
                                <tr class="border-b border-neutral-100 last:border-0 {{ $section->isArchived() ? 'opacity-60' : '' }}">
                                    <td class="py-2.5">
                                        <a href="{{ route('registrar.sections.show', $section) }}" class="font-semibold text-brand hover:underline">{{ $section->name }}</a>
                                        @if ($section->isArchived()) <x-badge color="neutral">Archived</x-badge> @endif
                                    </td>
                                    <td class="py-2.5">{{ $section->homeroomTeacher?->user?->name ?? '—' }}</td>
                                    <td class="py-2.5">{{ $students }} / {{ $section->capacity }}</td>
                                    <td class="py-2.5">
                                        <span title="Expected = the department's subjects, skipping ones coded for another grade (e.g. ENG10 for Grade 9)."
                                              class="font-semibold {{ $cov['expected'] && $cov['assigned'] >= $cov['expected'] ? 'text-success' : 'text-warning' }}">
                                            {{ $cov['assigned'] }} / {{ $cov['expected'] }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-right whitespace-nowrap">
                                        @include('registrar.sections.partials.row-actions', ['section' => $section])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Phone cards --}}
                <div class="md:hidden space-y-3">
                    @foreach ($group as $section)
                        @php $cov = $coverage[$section->id]; $students = $section->enrollments->where('status', 'Active')->count(); @endphp
                        <div class="rounded-xl border border-neutral-200 p-4 {{ $section->isArchived() ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('registrar.sections.show', $section) }}" class="font-semibold text-brand text-base">{{ $section->name }}</a>
                                @if ($section->isArchived()) <x-badge color="neutral">Archived</x-badge> @endif
                            </div>
                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                                <dt class="text-neutral-500">Homeroom</dt><dd>{{ $section->homeroomTeacher?->user?->name ?? '—' }}</dd>
                                <dt class="text-neutral-500">Students</dt><dd>{{ $students }} / {{ $section->capacity }}</dd>
                                <dt class="text-neutral-500">Subjects</dt><dd>{{ $cov['assigned'] }} / {{ $cov['expected'] }} with a teacher</dd>
                            </dl>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @include('registrar.sections.partials.row-actions', ['section' => $section, 'mobile' => true])
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @empty
            <x-card>
                <p class="text-sm text-neutral-400">
                    {{ $filters['search'] !== '' ? 'No sections match “'.$filters['search'].'”.' : 'No sections yet for this academic year.' }}
                </p>
            </x-card>
        @endforelse

        <x-card title="Place students" subtitle="Active students not yet placed in a section for the active academic year.">
            @error('student_ids')
                <div class="mb-4 rounded-lg border border-danger-line bg-danger-line/50 px-4 py-3 text-sm text-danger">{{ $message }}</div>
            @enderror
            @error('student_ids.*')
                <div class="mb-4 rounded-lg border border-danger-line bg-danger-line/50 px-4 py-3 text-sm text-danger">{{ $message }}</div>
            @enderror

            @if ($unplacedStudents->isEmpty())
                <p class="text-sm text-neutral-400">Every active student is placed in a section for this academic year.</p>
            @elseif ($activeSections->isEmpty())
                <p class="text-sm text-neutral-400">Create a section before placing students.</p>
            @else
                <form method="POST" action="#" x-data="{ section: '{{ $activeSections->first()->id }}' }"
                      x-bind:action="'{{ url('registrar/sections') }}/' + section + '/enroll'"
                      class="space-y-4">
                    @csrf
                    <div class="sm:w-72">
                        <label class="block text-sm font-semibold mb-1">Section</label>
                        <select x-model="section" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            @foreach ($activeSections as $section)
                                <option value="{{ $section->id }}">
                                    {{ $section->name }} ({{ $section->enrollments->where('status', 'Active')->count() }}/{{ $section->capacity }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-neutral-100 rounded-lg p-3">
                        @foreach ($unplacedStudents as $student)
                            <label class="flex items-center gap-2 text-sm py-1.5 px-2 rounded hover:bg-neutral-50 cursor-pointer min-h-[44px]">
                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="rounded border-neutral-300 text-brand">
                                <span class="font-semibold">{{ $student->name }}</span>
                                <span class="text-neutral-400 text-xs">{{ $student->student_id_number }} · {{ $student->department?->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark transition-colors">Place selected students</button>
                </form>
            @endif
        </x-card>

        <x-card title="Ownership">
            <p class="text-sm text-neutral-500">
                The Registrar creates sections and assigns homeroom teachers, and chooses which teacher teaches each subject in a section.
                The VP Academic owns the subject catalogue. Year-end promotion is prepared by the Registrar and applied only after VP + Principal co-approval.
            </p>
        </x-card>

        {{-- Create / edit drawer (full-screen sheet on phones) --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
            <div class="absolute inset-0 bg-black/40" @click="close()" x-show="open" x-transition.opacity></div>
            <div class="relative w-full sm:max-w-md h-full bg-white shadow-xl flex flex-col" x-show="open" x-transition>
                <div class="flex items-center justify-between border-b border-neutral-200 px-5 py-4">
                    <h2 id="drawer-title" class="text-lg font-bold" x-text="mode === 'edit' ? 'Edit section' : 'New section'"></h2>
                    <button type="button" @click="close()" aria-label="Close" class="w-11 h-11 flex items-center justify-center rounded-lg hover:bg-neutral-100">
                        <x-icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <form method="POST" :action="action" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="_form" :value="mode === 'edit' ? 'edit:' + form.id : 'create'">

                    <div>
                        <label for="d-name" class="block text-sm font-semibold mb-1">Section name</label>
                        <input id="d-name" type="text" name="name" x-model="form.name" required maxlength="255" placeholder="Grade 9-A"
                               class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label for="d-dept" class="block text-sm font-semibold mb-1">Department</label>
                        <select id="d-dept" name="department_id" x-model="form.department_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            <option value="">Choose…</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="mode === 'create'">
                        <label for="d-year" class="block text-sm font-semibold mb-1">Academic year</label>
                        <select id="d-year" name="academic_year_id" x-model="form.academic_year_id" :disabled="mode !== 'create'" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            @foreach ($academicYears as $year)
                                <option value="{{ $year->id }}">{{ $year->year_label }}{{ $year->is_active ? ' (active)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="d-home" class="block text-sm font-semibold mb-1">Homeroom teacher</label>
                        <select id="d-home" name="homeroom_teacher_id" x-model="form.homeroom_teacher_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            <option value="">Unassigned</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" data-homeroom="{{ $homerooms->get($teacher->id, collect())->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()->toJson() }}">{{ $teacher->user->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-warning" x-show="homeroomNote()" x-text="homeroomNote()"></p>
                        <p class="mt-1 text-xs text-neutral-400">Only staff with the Teacher role are listed.</p>
                    </div>
                    <div>
                        <label for="d-cap" class="block text-sm font-semibold mb-1">Capacity</label>
                        <input id="d-cap" type="number" name="capacity" x-model="form.capacity" min="1" max="100" required
                               class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                        <button type="button" @click="close()" class="w-full sm:w-auto border border-neutral-200 font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-neutral-50">Cancel</button>
                        <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark" x-text="mode === 'edit' ? 'Save changes' : 'Create section'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function sectionDrawer(sections, reopen, old) {
            const blank = { id: null, name: '', department_id: '', academic_year_id: '{{ $activeYear?->id }}', homeroom_teacher_id: '', capacity: 35 };
            return {
                open: false, mode: 'create', action: '{{ route('registrar.sections.store') }}', form: { ...blank },
                init() {
                    if (!reopen) return;
                    if (reopen === 'create') { this.openCreate(); }
                    else if (reopen.startsWith('edit:') && sections[reopen.slice(5)]) { this.openEdit(reopen.slice(5)); }
                    for (const k of ['name', 'department_id', 'academic_year_id', 'homeroom_teacher_id', 'capacity']) {
                        if (old[k] !== undefined && old[k] !== null) this.form[k] = String(old[k]);
                    }
                },
                openCreate() { this.mode = 'create'; this.action = '{{ route('registrar.sections.store') }}'; this.form = { ...blank }; this.open = true; this.focus(); },
                openEdit(id) {
                    const s = sections[id];
                    this.mode = 'edit'; this.action = s.action;
                    this.form = { ...blank, ...s, department_id: String(s.department_id), homeroom_teacher_id: s.homeroom_teacher_id ? String(s.homeroom_teacher_id) : '' };
                    this.open = true; this.focus();
                },
                close() { this.open = false; },
                focus() { this.$nextTick(() => document.getElementById('d-name')?.focus()); },
                homeroomNote() {
                    const opt = document.querySelector(`#d-home option[value="${this.form.homeroom_teacher_id}"]`);
                    if (!opt || !opt.dataset.homeroom) return '';
                    const others = JSON.parse(opt.dataset.homeroom).filter(s => String(s.id) !== String(this.form.id));
                    return others.length ? 'Already homeroom for ' + others.map(s => s.name).join(', ') + ' — you can still save.' : '';
                },
            };
        }
    </script>
</x-app-layout>
