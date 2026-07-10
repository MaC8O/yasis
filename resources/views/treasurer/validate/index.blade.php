<x-app-layout title="Validate &amp; Match Import" subtitle="Resolve unmatched rows and publish before they're visible to leadership and guardians." badge="Sun account, not Sun Plus" role="treasurer">
    <x-card>
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-semibold mb-1">Batch</label>
                <select name="batch" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($batches as $b)
                        <option value="{{ $b->id }}" @selected($batch && $b->id === $batch->id)>{{ $b->period }} — {{ $b->source_file }} ({{ $b->is_published ? 'Published' : 'Draft' }})</option>
                    @endforeach
                </select>
            </div>
        </form>
    </x-card>

    @if ($batch)
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <x-stat-tile label="Uploaded rows" color="blue">{{ $uploaded }}</x-stat-tile>
            <x-stat-tile label="Matched" color="green">{{ $matched }}</x-stat-tile>
            <x-stat-tile label="Unmatched" color="yellow">{{ $unmatchedRecords->count() }}</x-stat-tile>
            <x-stat-tile label="Restricted rows" color="blue">{{ $restrictedCount }}</x-stat-tile>
        </div>

        <x-card title="Unmatched rows" subtitle="Rows whose accounting identifier does not match an ISMS student — never silently dropped.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Source ref</th>
                        <th class="py-2 font-semibold">Date</th>
                        <th class="py-2 font-semibold">Amount</th>
                        <th class="py-2 font-semibold">Balance</th>
                        <th class="py-2 font-semibold">Map to student</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unmatchedRecords as $row)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $row->raw_student_key }}</td>
                            <td class="py-2.5">{{ $row->txn_date->format('M j, Y') }}</td>
                            <td class="py-2.5">{{ number_format($row->amount) }}</td>
                            <td class="py-2.5">{{ number_format($row->balance) }}</td>
                            <td class="py-2.5">
                                <form method="POST" action="{{ route('treasurer.validate.resolve', $row) }}" class="flex gap-2">
                                    @csrf
                                    <input type="text" name="student_id_number" placeholder="YAS-2026-0001" required
                                        class="flex-1 rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                    <button type="submit" class="text-xs font-semibold bg-[#1F573D] text-white rounded-lg px-3 py-1.5">Match</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-neutral-400">No unmatched rows in this batch.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card title="Publish">
            @if ($batch->is_published)
                <x-badge color="green">Published {{ $batch->published_at->format('M j, Y H:i') }}</x-badge>
            @else
                <p class="text-sm text-neutral-500 mb-4">Publishing makes matched records visible to leadership (read-only) and guardians/students. Unmatched rows stay hidden until resolved.</p>
                <form method="POST" action="{{ route('treasurer.validate.publish', $batch) }}">
                    @csrf
                    <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Publish Valid Rows</button>
                </form>
            @endif
        </x-card>
    @else
        <x-card><p class="text-sm text-neutral-400">No import batches yet. Start from Import Records.</p></x-card>
    @endif
</x-app-layout>
