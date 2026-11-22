{{-- VP: give back points of one deduction that was a mistake. $entry, $left (points still restorable). --}}
<details class="text-sm">
    <summary class="cursor-pointer font-semibold text-brand">Restore points from this deduction ({{ $left }} left)…</summary>
    <form method="POST" action="{{ route('behavior.entries.restore', $entry) }}" class="mt-2 flex flex-col sm:flex-row gap-2 sm:items-end">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-neutral-600" for="rp-{{ $entry->id }}">Points</label>
            <input id="rp-{{ $entry->id }}" type="number" name="points" min="1" max="{{ $left }}" step="1" value="{{ $left }}" required class="w-24 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
        </div>
        <div class="flex-1">
            <label class="block text-xs font-semibold text-neutral-600" for="rr-{{ $entry->id }}">Reason (required, shown to the family)</label>
            <input id="rr-{{ $entry->id }}" type="text" name="reason" required minlength="5" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-white border border-neutral-300 font-semibold rounded-lg px-4 py-2 text-sm hover:border-brand">Restore</button>
    </form>
</details>
