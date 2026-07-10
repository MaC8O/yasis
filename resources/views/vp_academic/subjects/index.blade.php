<x-app-layout title="Subjects & Teaching" subtitle="Own the subject catalogue and assign subject teachers — sections and homerooms are managed by the Registrar." badge="VP Academic" role="vp_academic">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Subject catalogue" subtitle="Subjects offered per department.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Code</th>
                            <th class="py-2 font-semibold">Subject</th>
                            <th class="py-2 font-semibold">Department</th>
                            <th class="py-2 font-semibold">Assignments</th>
                            <th class="py-2 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subjects as $subject)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5 font-mono text-xs">{{ $subject->code }}</td>
                                <td class="py-2.5">{{ $subject->name }}</td>
                                <td class="py-2.5 text-neutral-500">{{ $subject->department?->name }}</td>
                                <td class="py-2.5">{{ $subject->teaching_assignments_count }}</td>
                                <td class="py-2.5 text-right">
                                    @if ($subject->teaching_assignments_count === 0)
                                        <form method="POST" action="{{ route('vp_academic.subjects.destroy', $subject) }}"
                                            onsubmit="return confirm('Remove {{ $subject->code }} from the catalogue?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-neutral-400">No subjects in the catalogue yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Add subject">
            <form method="POST" action="{{ route('vp_academic.subjects.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" required placeholder="MATH9"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Department</label>
                        <select name="department_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold mb-1">Subject name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Mathematics"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                </div>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add to catalogue</button>
            </form>
        </x-card>
    </div>

    <x-card title="Subject-teaching assignments (active year)" subtitle="Which teacher teaches which subject in which section. Gradebooks and score entry follow these assignments.">
        <form method="POST" action="{{ route('vp_academic.assignments.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end mb-6">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Section</label>
                <select name="section_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Subject</label>
                <select name="subject_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->code }} — {{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Teacher</label>
                <select name="teacher_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->user?->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Assign</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Section</th>
                        <th class="py-2 font-semibold">Subject</th>
                        <th class="py-2 font-semibold">Teacher</th>
                        <th class="py-2 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $assignment->section->name }}</td>
                            <td class="py-2.5">{{ $assignment->subject->code }} — {{ $assignment->subject->name }}</td>
                            <td class="py-2.5 text-neutral-500">{{ $assignment->teacher?->user?->name ?? '—' }}</td>
                            <td class="py-2.5 text-right">
                                <form method="POST" action="{{ route('vp_academic.assignments.destroy', $assignment) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-neutral-400">No teaching assignments for the active year yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
