<x-app-layout title="Teacher Class Assignment" subtitle="Pick a class, then set the teacher for each subject. Admin setup/override — operationally the Registrar owns sections & homeroom and the VP Academic owns subject-teaching." badge="Admin" role="admin">
    @if ($sections->isEmpty())
        <x-card><p class="text-sm text-neutral-400">No sections exist for the active academic year{{ $activeYear ? " ({$activeYear->year_label})" : '' }}. Create sections in the Registrar portal (or seed the year first).</p></x-card>
    @else
    <div x-data="{
            selected: localStorage.getItem('ta_section') || '{{ $sections->first()->id }}',
            pick(id) { this.selected = String(id); localStorage.setItem('ta_section', this.selected); }
         }"
         class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

        {{-- Class picker --}}
        <x-card title="Classes" class="self-start">
            <div class="space-y-4">
                @foreach ($sections->groupBy('department.name') as $deptName => $deptSections)
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-400 mb-1.5">{{ $deptName }}</p>
                        <div class="space-y-1">
                            @foreach ($deptSections as $section)
                                @php
                                    $deptSubjects = $subjectsByDept[$section->department_id] ?? collect();
                                    $assignedCount = $section->teachingAssignments->whereNotNull('teacher_id')->count();
                                    $total = $deptSubjects->count();
                                @endphp
                                <button type="button" @click="pick('{{ $section->id }}')"
                                        :class="selected === '{{ $section->id }}' ? 'bg-[#1F573D] text-white' : 'hover:bg-neutral-100 text-neutral-700'"
                                        class="w-full text-left rounded-lg px-3 py-2 text-sm font-medium flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $section->name }}</span>
                                    <span :class="selected === '{{ $section->id }}' ? 'text-white/70' : 'text-neutral-400'" class="text-xs shrink-0">{{ $assignedCount }}/{{ $total }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Per-section editor --}}
        <div>
            @foreach ($sections as $section)
                @php
                    $deptSubjects = $subjectsByDept[$section->department_id] ?? collect();
                    $bySubject = $section->teachingAssignments->keyBy('subject_id');
                    $assignedCount = $section->teachingAssignments->whereNotNull('teacher_id')->count();
                    $total = $deptSubjects->count();
                @endphp
                <div x-show="selected === '{{ $section->id }}'" x-cloak class="space-y-6">
                    {{-- Header + homeroom --}}
                    <x-card>
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold">{{ $section->name }}</h2>
                                <p class="text-sm text-neutral-500">{{ $section->department->name }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold {{ $assignedCount === $total && $total > 0 ? 'text-[#1F573D]' : 'text-neutral-800' }}">{{ $assignedCount }}<span class="text-neutral-300">/{{ $total }}</span></p>
                                <p class="text-xs text-neutral-400">subjects assigned</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.teacher-assignments.homeroom', $section) }}" class="flex flex-wrap gap-3 items-end mt-4 pt-4 border-t border-neutral-100">
                            @csrf @method('PUT')
                            <div class="w-64">
                                <label class="block text-sm font-semibold mb-1">Homeroom teacher</label>
                                <select name="homeroom_teacher_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                                    <option value="">Unassigned</option>
                                    @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" @selected($section->homeroom_teacher_id === $teacher->id)>{{ $teacher->user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="text-sm font-semibold text-[#1F573D] border border-[#1F573D] rounded-lg px-4 py-2 hover:bg-[#1F573D]/5">Save homeroom</button>
                        </form>
                    </x-card>

                    {{-- Subject → teacher matrix --}}
                    <x-card title="Subjects" subtitle="Set or change the teacher for each subject. Choose “Unassigned” to clear.">
                        @if ($deptSubjects->isEmpty())
                            <p class="text-sm text-neutral-400">No subjects defined for {{ $section->department->name }} yet.</p>
                        @else
                            <div class="divide-y divide-neutral-100">
                                @foreach ($deptSubjects as $subject)
                                    @php $assignment = $bySubject->get($subject->id); @endphp
                                    <form method="POST" action="{{ route('admin.teacher-assignments.set') }}" class="flex flex-wrap items-center gap-3 py-2.5">
                                        @csrf
                                        <input type="hidden" name="section_id" value="{{ $section->id }}">
                                        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold truncate">{{ $subject->name }}</p>
                                            <p class="text-xs text-neutral-400">{{ $subject->code }}</p>
                                        </div>
                                        @if (! $assignment)
                                            <span class="text-xs font-medium text-neutral-400 bg-neutral-100 rounded-full px-2 py-0.5">Unassigned</span>
                                        @endif
                                        <select name="teacher_id" class="w-56 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                                            <option value="">— Unassigned —</option>
                                            @foreach ($teachers as $teacher)
                                                <option value="{{ $teacher->id }}" @selected($assignment && $assignment->teacher_id === $teacher->id)>{{ $teacher->user->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="text-sm font-semibold text-[#1F573D] hover:underline">Save</button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </x-card>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Teacher workload roster --}}
    <x-card title="Teacher workload" subtitle="Subjects taught and homerooms held across all classes — spot over- and under-loaded teachers.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Teacher</th>
                        <th class="py-2 font-semibold text-right">Subjects taught</th>
                        <th class="py-2 font-semibold text-right">Homerooms</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teachers as $teacher)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $teacher->user->name }}</td>
                            <td class="py-2.5 text-right tabular-nums">{{ $teacherLoads[$teacher->id] ?? 0 }}</td>
                            <td class="py-2.5 text-right tabular-nums">{{ $homeroomLoads[$teacher->id] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    @endif
</x-app-layout>
