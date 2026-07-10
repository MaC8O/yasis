<x-app-layout title="Grade Scale" subtitle="Placeholder scale, pending the school's grade-scale documents." badge="Admin" role="admin">
    <x-card title="Add band">
        <form method="POST" action="{{ route('admin.grade-scale.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Department</label>
                <select name="department_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Letter</label>
                <input type="text" name="letter" maxlength="5" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Min score</label>
                <input type="number" step="0.01" name="min_score" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">GPA point</label>
                <input type="number" step="0.01" name="gpa_point" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add</button>
        </form>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($departments as $department)
            <x-card :title="$department->name">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Letter</th>
                            <th class="py-2 font-semibold">Min score</th>
                            <th class="py-2 font-semibold">GPA</th>
                            <th class="py-2 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($department->gradeScaleBands as $band)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5 font-semibold">{{ $band->letter }}</td>
                                <td class="py-2.5">{{ number_format($band->min_score, 2) }}</td>
                                <td class="py-2.5">{{ number_format($band->gpa_point, 2) }}</td>
                                <td class="py-2.5 text-right">
                                    <form method="POST" action="{{ route('admin.grade-scale.destroy', $band) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
