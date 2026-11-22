{{-- Incident fields shared by the teacher form and the VP "new report with deduction" form. $categories, $report (nullable). --}}
<div>
    <label class="block text-sm font-semibold mb-1" for="incident_date">Date of the incident</label>
    <input id="incident_date" type="date" name="incident_date" required max="{{ now()->toDateString() }}"
           value="{{ old('incident_date', $report?->incident_date?->toDateString() ?? now()->toDateString()) }}"
           class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
</div>
<div>
    <label class="block text-sm font-semibold mb-1" for="behavior_category_id">Category</label>
    <select id="behavior_category_id" name="behavior_category_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
        <option value="">Choose a category…</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((int) old('behavior_category_id', $report?->behavior_category_id) === $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
</div>
<div class="sm:col-span-2">
    <label class="block text-sm font-semibold mb-1" for="description">What happened</label>
    <textarea id="description" name="description" rows="4" required minlength="10" maxlength="2000"
              class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">{{ old('description', $report?->description) }}</textarea>
    <p class="text-xs text-neutral-500 mt-1">The student and their guardians can read this.</p>
</div>
<div class="sm:col-span-2">
    <label class="inline-flex items-center gap-2 text-sm font-semibold min-h-11">
        <input type="hidden" name="is_office_referral" value="0">
        <input type="checkbox" name="is_office_referral" value="1" class="w-5 h-5 rounded border-neutral-300" @checked(old('is_office_referral', $report?->is_office_referral))>
        I sent the student to the office (office referral)
    </label>
</div>
