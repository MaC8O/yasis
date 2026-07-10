<x-app-layout title="Teacher Class Assignment" subtitle="Initial technical setup and override. Operationally, the Registrar owns sections & homeroom and the VP Academic owns subject-teaching assignments." badge="Admin" role="admin">
    <x-card title="Assign a subject teacher" subtitle="One teacher per subject per section — assigning an already-assigned subject is refused; use Reassign on the row instead.">
        <form method="POST" action="{{ route('admin.teacher-assignments.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Section</label>
                <select name="section_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }} ({{ $section->department->name }})</option>
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
                        <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Assign</button>
        </form>
    </x-card>

    @forelse ($sections as $section)
        <x-card :title="$section->name" :subtitle="$section->department->name">
            <form method="POST" action="{{ route('admin.teacher-assignments.homeroom', $section) }}" class="flex flex-wrap gap-3 items-end mb-4 pb-4 border-b border-neutral-100">
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

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Subject</th>
                        <th class="py-2 font-semibold">Teacher</th>
                        <th class="py-2 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section->teachingAssignments as $assignment)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $assignment->subject->code }} — {{ $assignment->subject->name }}</td>
                            <td class="py-2.5">{{ $assignment->teacher->user->name }}</td>
                            <td class="py-2.5 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.teacher-assignments.reassign', $assignment) }}" class="inline-flex gap-2 items-center">
                                    @csrf @method('PUT')
                                    <select name="teacher_id" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                        @foreach ($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" @selected($assignment->teacher_id === $teacher->id)>{{ $teacher->user->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-xs font-semibold text-[#1F573D] hover:underline">Reassign</button>
                                </form>
                                <form method="POST" action="{{ route('admin.teacher-assignments.destroy', $assignment) }}" class="inline ml-3">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-neutral-400">No subject assignments yet for this section.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    @empty
        <x-card><p class="text-sm text-neutral-400">No sections exist for the active academic year. Create sections in the Registrar portal (or seed the year first).</p></x-card>
    @endforelse
</x-app-layout>
