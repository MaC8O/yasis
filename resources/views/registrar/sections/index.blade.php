<x-app-layout title="Class Sections" subtitle="Create sections, assign homeroom teachers, and place students — Registrar-owned." badge="Registrar" role="registrar">
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div>
                <p class="text-sm font-semibold mb-1">Academic year</p>
                <p class="bg-neutral-50 border border-neutral-200 rounded-lg px-3 py-2.5 text-sm">{{ $activeYear?->year_label ?? 'Not set' }}</p>
            </div>
            <form method="GET" class="sm:col-span-2 flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-semibold mb-1">Department</label>
                    <select name="department" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department'] ?? '') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Filter</button>
            </form>
        </div>
    </x-card>

    <x-card title="Add section">
        <form method="POST" action="{{ route('registrar.sections.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Name</label>
                <input type="text" name="name" required placeholder="Grade 9-A" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Department</label>
                <select name="department_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Homeroom teacher</label>
                <select name="homeroom_teacher_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">Unassigned</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Capacity</label>
                <input type="number" name="capacity" value="35" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add Section</button>
        </form>
    </x-card>

    <x-card title="Place students" subtitle="Active students not yet placed in a section for this academic year.">
        @error('student_ids')
            <div class="mb-4 rounded-lg border border-[#F2D9D4] bg-[#F2D9D4]/50 px-4 py-3 text-sm text-[#B0392B]">{{ $message }}</div>
        @enderror
        @error('student_ids.*')
            <div class="mb-4 rounded-lg border border-[#F2D9D4] bg-[#F2D9D4]/50 px-4 py-3 text-sm text-[#B0392B]">{{ $message }}</div>
        @enderror

        @if ($unplacedStudents->isEmpty())
            <p class="text-sm text-neutral-400">Every active student is placed in a section for this academic year.</p>
        @elseif ($sections->isEmpty())
            <p class="text-sm text-neutral-400">Create a section above before placing students.</p>
        @else
            <form method="POST" action="#" x-data="{ section: '{{ $sections->first()->id }}' }"
                  x-bind:action="'{{ url('registrar/sections') }}/' + section + '/enroll'"
                  class="space-y-4">
                @csrf
                <div class="sm:w-72">
                    <label class="block text-sm font-semibold mb-1">Section</label>
                    <select x-model="section" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}">
                                {{ $section->name }} ({{ $section->enrollments->count() }}/{{ $section->capacity }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 max-h-64 overflow-y-auto border border-neutral-100 rounded-lg p-3">
                    @foreach ($unplacedStudents as $student)
                        <label class="flex items-center gap-2 text-sm py-1.5 px-2 rounded hover:bg-neutral-50 cursor-pointer">
                            <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="rounded border-neutral-300 text-[#1F573D]">
                            <span class="font-semibold">{{ $student->first_name }} {{ $student->last_name }}</span>
                            <span class="text-neutral-400 text-xs">{{ $student->student_id_number }} · {{ $student->department?->name }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Place selected students</button>
            </form>
        @endif
    </x-card>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Sections" color="blue">{{ $stats['sections'] }}</x-stat-tile>
        <x-stat-tile label="Assigned teachers" color="blue">{{ $stats['assignedTeachers'] }}</x-stat-tile>
        <x-stat-tile label="Students placed" color="green">{{ $stats['studentsPlaced'] }}</x-stat-tile>
        <x-stat-tile label="Open seats" color="yellow">{{ $stats['openSeats'] }}</x-stat-tile>
    </div>

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Section</th>
                    <th class="py-2 font-semibold">Homeroom Teacher</th>
                    <th class="py-2 font-semibold">Students</th>
                    <th class="py-2 font-semibold">Capacity</th>
                    <th class="py-2 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sections as $section)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5 font-semibold">{{ $section->name }}</td>
                        <td class="py-2.5">{{ $section->homeroomTeacher?->user?->name ?? '—' }}</td>
                        <td class="py-2.5">{{ $section->enrollments->count() }}</td>
                        <td class="py-2.5">{{ $section->capacity }}</td>
                        <td class="py-2.5">
                            <form method="POST" action="{{ route('registrar.sections.update', $section) }}" class="flex gap-2 items-center">
                                @csrf @method('PUT')
                                <select name="homeroom_teacher_id" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                    <option value="">Unassigned</option>
                                    @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" @selected($section->homeroom_teacher_id === $teacher->id)>{{ $teacher->user->name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="capacity" value="{{ $section->capacity }}" class="w-16 rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                <button type="submit" class="text-xs font-semibold text-[#1F573D] hover:underline">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-neutral-400">No sections yet for this academic year.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Ownership">
        <p class="text-sm text-neutral-500">
            The Registrar creates sections and assigns the homeroom teacher; the VP Academic owns subject-teaching assignments.
            Year-end promotion is prepared by the Registrar and applied only after VP + Principal co-approval.
        </p>
        <a href="{{ route('registrar.teaching-assignments.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Teacher Assignment</a>
    </x-card>
</x-app-layout>
