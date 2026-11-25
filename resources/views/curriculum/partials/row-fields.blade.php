{{-- Editable fields of one curriculum row. $row is the offering (or null when adding); $prefix keeps ids unique. --}}
@php
    $mine = old('_row') == ($row?->id ?? 'new');
    $val = fn ($field, $default) => $mine ? old($field, $default) : $default;
    $hours = $row?->credit_hours === null ? '' : rtrim(rtrim((string) $row->credit_hours, '0'), '.');
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="sm:col-span-2">
        <label for="{{ $prefix }}-label" class="block text-sm font-semibold mb-1">Label on the report card</label>
        <input id="{{ $prefix }}-label" type="text" name="report_label" maxlength="60" required value="{{ $val('report_label', $row?->report_label) }}"
               class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
    </div>
    <div>
        <label for="{{ $prefix }}-mode" class="block text-sm font-semibold mb-1">Grading</label>
        <select id="{{ $prefix }}-mode" name="grading_mode" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
            @foreach (\App\Models\SubjectOffering::GRADING_MODES as $mode => $label)
                <option value="{{ $mode }}" @selected($val('grading_mode', $row?->grading_mode ?? 'score') === $mode)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $prefix }}-hours" class="block text-sm font-semibold mb-1">Credit hours <span class="font-normal text-neutral-400">(Grades 11-12 GPA)</span></label>
        <input id="{{ $prefix }}-hours" type="text" inputmode="decimal" name="credit_hours" maxlength="4" value="{{ $val('credit_hours', $hours) }}" placeholder="none"
               class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
    </div>
    <label class="flex items-center gap-2 text-sm min-h-[44px]">
        <input type="hidden" name="is_compulsory" value="0">
        <input type="checkbox" name="is_compulsory" value="1" @checked($val('is_compulsory', $row?->is_compulsory ?? true)) class="rounded border-neutral-300 text-brand">
        Compulsory
    </label>
    <div>
        <label for="{{ $prefix }}-slot" class="block text-sm font-semibold mb-1">Elective slot <span class="font-normal text-neutral-400">(optional subjects only)</span></label>
        <input id="{{ $prefix }}-slot" type="text" name="elective_slot" maxlength="10" value="{{ $val('elective_slot', $row?->elective_slot) }}" placeholder="e.g. A"
               class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm uppercase">
    </div>
    <label class="flex items-center gap-2 text-sm min-h-[44px] sm:col-span-2">
        <input type="hidden" name="counts_in_result" value="0">
        <input type="checkbox" name="counts_in_result" value="1" @checked($val('counts_in_result', $row?->counts_in_result ?? true)) class="rounded border-neutral-300 text-brand">
        Counts in the result (average, point total or GPA)
    </label>
</div>
