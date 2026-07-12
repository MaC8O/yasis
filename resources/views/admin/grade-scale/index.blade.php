<x-app-layout title="Grade Scale" subtitle="Academic grading bands per teaching department — the YASIS standard scale." badge="Admin" role="admin">
    <x-card title="How the YASIS scale works" subtitle="Applies to teaching departments only.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                <p class="font-semibold mb-1">Secondary (Middle &amp; High School)</p>
                <p class="text-neutral-500">Letter grades <strong>A · B+ · B · C+ · C · D · F</strong> with GPA points (4.0 → 0.0), used to compute term and cumulative GPA.</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                <p class="font-semibold mb-1">Lower school (Pre-School &amp; Elementary)</p>
                <p class="text-neutral-500">Descriptive marks <strong>E · G · S · P · N</strong> (Excellent → Needs support), no GPA — per the lower-school reporting format.</p>
            </div>
        </div>
    </x-card>

    <x-card title="Add a band manually" subtitle="Leave GPA blank for descriptive (lower-school) scales.">
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
                <label class="block text-sm font-semibold mb-1">GPA point <span class="text-neutral-400 font-normal">(optional)</span></label>
                <input type="number" step="0.01" name="gpa_point" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add</button>
        </form>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($departments as $department)
            @php $usesGpa = $department->level === 'Secondary'; @endphp
            <x-card>
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-bold">{{ $department->name }}</h2>
                        <p class="text-sm text-neutral-500">{{ $usesGpa ? 'Letter + GPA scale' : 'Descriptive scale (no GPA)' }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.grade-scale.defaults', $department) }}"
                          onsubmit="return confirm('Replace {{ $department->name }}\'s scale with the YASIS standard? Existing bands will be removed.');">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-[#1F573D] border border-[#1F573D] rounded-lg px-3 py-1.5 hover:bg-[#1F573D]/5 whitespace-nowrap">Load YASIS standard</button>
                    </form>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Grade</th>
                            <th class="py-2 font-semibold">Min score</th>
                            <th class="py-2 font-semibold">{{ $usesGpa ? 'GPA' : '' }}</th>
                            <th class="py-2 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($department->gradeScaleBands as $band)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5 font-semibold">{{ $band->letter }}</td>
                                <td class="py-2.5">{{ rtrim(rtrim(number_format($band->min_score, 2), '0'), '.') }}+</td>
                                <td class="py-2.5">{{ is_null($band->gpa_point) ? '—' : number_format($band->gpa_point, 2) }}</td>
                                <td class="py-2.5 text-right">
                                    <form method="POST" action="{{ route('admin.grade-scale.destroy', $band) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-neutral-400">No bands yet — click “Load YASIS standard”.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
