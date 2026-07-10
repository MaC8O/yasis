<x-app-layout title="Prepare Promotion Batch" :subtitle="$section->name.' — '.$section->department->name" badge="Registrar" role="registrar">
    <form method="POST" action="{{ route('registrar.promotions.store', $section) }}">
        @csrf
        <x-card title="Roster actions" subtitle="Choose Promote / Retain / Graduate for each student.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Student</th>
                        <th class="py-2 font-semibold">Action</th>
                        <th class="py-2 font-semibold">Target section</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section->enrollments as $i => $enrollment)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">
                                {{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }}
                                <input type="hidden" name="actions[{{ $i }}][student_id]" value="{{ $enrollment->student_id }}">
                            </td>
                            <td class="py-2.5">
                                <select name="actions[{{ $i }}][action]" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                    <option value="Promote">Promote</option>
                                    <option value="Retain">Retain</option>
                                    <option value="Graduate">Graduate</option>
                                </select>
                            </td>
                            <td class="py-2.5">
                                <select name="actions[{{ $i }}][to_section_id]" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                    <option value="">—</option>
                                    @foreach ($targetSections as $target)
                                        <option value="{{ $target->id }}">{{ $target->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-neutral-400">No students enrolled in this section.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Prepare batch</button>
            <a href="{{ route('registrar.promotions.index') }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
        </div>
    </form>
</x-app-layout>
