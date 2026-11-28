@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $flags = $template->flatMap->pendingQuestions()->unique()->values();
@endphp
<x-app-layout title="Gradebook Template" :subtitle="$readOnly
        ? 'The fixed gradebook categories for KG1 to Grade 12. Set by the Admin; shown here read-only.'
        : 'The fixed gradebook categories every KG1 to Grade 12 gradebook uses. Teachers add their own items inside them.'"
    :badge="$readOnly ? 'Principal' : 'Admin'" :role="$portal">

    <x-card title="Categories" subtitle="A category's score is the marks earned over the marks possible across its items. Attendance has no items: it comes from the attendance records.">
        @if ($flags->isNotEmpty())
            <div class="mb-4 rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning">
                Uses defaults that <span class="font-semibold">need confirmation from the school</span> ({{ $flags->implode(', ') }} in the rules file):
                @php $att = \App\Services\AttendanceScoreService::rules(); @endphp
                Present and Late count as attended, Absent counts against,
                {{ $att['excused'] === 'count_as_absent' ? 'an excused absence counts as an absence' : 'Excused days are left out' }},
                @if ($att['lates_per_absence'] > 0) every {{ $att['lates_per_absence'] }} late arrivals count as one absence, @endif
                attendance in each subject is the daily attendance, and only the days a student has a record for count. The Admin sets these on Report Card Rules.
            </div>
        @endif

        @if ($readOnly)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-neutral-500 border-b border-neutral-200"><th class="py-2 font-semibold">Category</th><th class="py-2 font-semibold">Weight</th><th class="py-2 font-semibold">Scored from</th></tr></thead>
                    <tbody>
                        @foreach ($template as $category)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5 font-semibold">{{ $category->name }}</td>
                                <td class="py-2.5">{{ $num($category->weight_pct) }}%</td>
                                <td class="py-2.5 text-neutral-600">{{ $category->isAttendance() ? 'Attendance records' : 'Items the teacher adds' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <form method="POST" action="{{ route('admin.gradebook-template.update') }}" x-data="{ w: @js($template->mapWithKeys(fn ($c) => [$c->key => $num(old('categories.'.$c->key.'.weight', $c->weight_pct))])) }">
                @csrf @method('PUT')
                <div class="space-y-3">
                    @foreach ($template as $category)
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end rounded-lg border border-neutral-200 p-3">
                            <div class="sm:col-span-7">
                                <label for="cat-{{ $category->key }}" class="block text-xs font-semibold mb-1">Name</label>
                                <input id="cat-{{ $category->key }}" name="categories[{{ $category->key }}][name]" maxlength="80" required
                                       value="{{ old('categories.'.$category->key.'.name', $category->name) }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="w-{{ $category->key }}" class="block text-xs font-semibold mb-1">Weight %</label>
                                <input id="w-{{ $category->key }}" name="categories[{{ $category->key }}][weight]" inputmode="decimal" required x-model="w['{{ $category->key }}']"
                                       class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                            </div>
                            <p class="sm:col-span-3 text-xs text-neutral-500 sm:pb-2">{{ $category->isAttendance() ? 'From attendance records' : 'Teachers add items' }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-sm">Total: <span class="font-semibold" x-text="Object.values(w).reduce((s, v) => s + (parseFloat(v) || 0), 0).toFixed(2).replace(/\.?0+$/, '') + '%'"></span> <span class="text-neutral-500">(must be 100%)</span></p>
                @if ($flags->isNotEmpty())
                    <label class="mt-3 flex items-center gap-2 text-sm min-h-[44px]">
                        <input type="hidden" name="confirmed" value="0">
                        <input type="checkbox" name="confirmed" value="1" class="rounded border-neutral-300 text-brand">
                        Confirmed by the school (clears the {{ $flags->implode(', ') }} flag)
                    </label>
                @endif
                <p class="mt-2 text-xs text-neutral-500">Saving updates the open terms' gradebooks too. Locked terms keep the categories and results they were locked with.</p>
                <button type="submit" class="mt-3 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save template</button>
            </form>
        @endif
    </x-card>

    <x-card title="Where the template is used" subtitle="Set per grading scheme. Shown read-only until the school answers Q9.">
        <ul class="divide-y divide-neutral-100 text-sm">
            @foreach ($schemes as $scheme)
                <li class="py-2.5 flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span class="w-40 font-semibold">{{ $scheme->name }}</span>
                    <span class="{{ $scheme->uses_gradebook_template ? 'text-success' : 'text-neutral-500' }}">{{ $scheme->uses_gradebook_template ? 'Uses the template' : 'Teachers set their own categories and weights' }}</span>
                    <span class="text-xs text-neutral-400">{{ $scheme->gradeLevels->pluck('name')->implode(', ') }}</span>
                    @if (in_array('Q9', $scheme->pendingQuestions(), true))
                        <span class="rounded-full bg-tint-yellow text-tint-yellow-ink px-2.5 py-0.5 text-xs font-semibold">Needs confirmation from the school (Q9)</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</x-app-layout>
