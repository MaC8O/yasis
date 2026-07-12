<x-app-layout title="Teacher Assignment" subtitle="Assign subject teachers to sections for the active academic year." badge="Registrar" role="registrar">
    <x-card title="Assign a teacher">
        <form method="POST" action="{{ route('registrar.teaching-assignments.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
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
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
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

    <x-card title="Current assignments">
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
                        <td class="py-2.5">{{ $assignment->subject->name }}</td>
                        <td class="py-2.5">{{ $assignment->teacher->user->name }}</td>
                        <td class="py-2.5 text-right">
                            <form method="POST" action="{{ route('registrar.teaching-assignments.destroy', $assignment) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No assignments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <a href="{{ route('registrar.sections.index') }}" class="inline-block text-sm font-semibold text-neutral-500 hover:underline">Back to sections</a>
</x-app-layout>
