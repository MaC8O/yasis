@use('App\Support\Money')
<x-app-layout title="Import History" subtitle="Every file you've imported, kept as a batch. Open one to review it, or revert a bad batch to remove exactly its rows." badge="Treasurer" role="treasurer">
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Batches" color="blue">{{ $stats['total'] }}</x-stat-tile>
        <x-stat-tile label="Published" color="green" hint="Visible to leadership and guardians">{{ $stats['published'] }}</x-stat-tile>
        <x-stat-tile label="Drafts to review" color="yellow" hint="Not yet visible to anyone else">{{ $stats['needsReview'] }}</x-stat-tile>
    </div>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200 bg-neutral-50">
                        <th class="py-2.5 pl-5 pr-2 text-left font-semibold">Batch</th>
                        <th class="py-2.5 px-2 text-right font-semibold">Rows</th>
                        <th class="py-2.5 px-2 text-left font-semibold">Matching</th>
                        <th class="py-2.5 px-2 text-right font-semibold">Charged</th>
                        <th class="py-2.5 px-2 text-right font-semibold">Still owed</th>
                        <th class="py-2.5 px-2 text-left font-semibold">Status</th>
                        <th class="py-2.5 pl-2 pr-5 text-right font-semibold"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr class="border-b border-neutral-100 last:border-0 align-top">
                            <td class="py-3 pl-5 pr-2">
                                <p class="font-semibold text-ink">{{ $batch->period }} <span class="text-xs font-normal text-neutral-400">#{{ $batch->id }}</span></p>
                                <p class="text-xs text-neutral-500">{{ $batch->source_file }}</p>
                                <p class="text-xs text-neutral-400">Imported {{ $batch->uploaded_at?->format('d M Y') }}{{ $batch->uploadedBy?->user ? ' by '.$batch->uploadedBy->user->name : '' }}</p>
                            </td>
                            <td class="py-3 px-2 text-right tabular-nums font-semibold">{{ $batch->imported_fee_records_count }}</td>
                            <td class="py-3 px-2 text-xs leading-5">
                                <span class="text-success font-semibold">{{ $batch->matched_count }} matched</span>
                                @if ($batch->unmatched_count)<br><span class="text-danger font-semibold">{{ $batch->unmatched_count }} need a match</span>@endif
                                @if ($batch->held_count)<br><span class="text-neutral-500">{{ $batch->held_count }} held</span>@endif
                                @if ($batch->restricted_count)<br><span class="text-tint-purple-ink">{{ $batch->restricted_count }} SDA</span>@endif
                            </td>
                            <td class="py-3 px-2 text-right tabular-nums">{{ Money::format($batch->charged_sum) }}</td>
                            <td class="py-3 px-2 text-right tabular-nums font-semibold">{{ Money::format($batch->owed_sum, true) }}</td>
                            <td class="py-3 px-2">
                                @if ($batch->is_published)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-success"><x-icon name="check" class="w-3.5 h-3.5" /> Published</span>
                                    <p class="text-xs text-neutral-400">{{ $batch->published_at->format('d M Y') }}</p>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-warning"><x-icon name="clock" class="w-3.5 h-3.5" /> Draft</span>
                                    <p class="text-xs text-neutral-400">Not visible yet</p>
                                @endif
                            </td>
                            <td class="py-3 pl-2 pr-5 text-right whitespace-nowrap">
                                <a href="{{ route('treasurer.validate.index', ['batch' => $batch->id]) }}" class="text-xs font-semibold text-brand hover:underline mr-3">Review</a>
                                <form method="POST" action="{{ route('treasurer.history.revert', $batch) }}" class="inline"
                                      data-confirm="Revert batch {{ $batch->period }}? This permanently removes its {{ $batch->imported_fee_records_count }} rows{{ $batch->is_published ? ', including from what leadership and guardians see' : '' }}. Other batches are not touched, and the revert is recorded in the audit log."
                                      onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-danger hover:underline">Revert</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 px-5 text-center text-neutral-500">No imports yet. Start from <a href="{{ route('treasurer.import.index') }}" class="font-semibold text-brand hover:underline">Import Records</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="px-5 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs text-neutral-500">
            Amounts in {{ Money::CURRENCY }}. Uploads, matches, SDA changes, publishing and reverts are all recorded in the audit log.
            Reverting removes exactly that batch's rows — fix the file in the accounting system and import it again.
        </p>
    </div>
</x-app-layout>
