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
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
            <x-stat-tile label="Uploaded rows" color="blue">{{ $uploaded }}</x-stat-tile>
            <x-stat-tile label="Matched" color="green">{{ $matched }}</x-stat-tile>
            <x-stat-tile label="Unmatched" color="yellow">{{ $unmatchedRecords->count() }}</x-stat-tile>
            <x-stat-tile label="Restricted rows" color="blue">{{ $restrictedCount }}</x-stat-tile>
            <x-stat-tile label="Held rows" color="yellow">{{ $heldCount }}</x-stat-tile>
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

        <x-card title="All rows" subtitle="Restrict hides a row from guardians/students (SDA allowance); Hold parks it out of publishing.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Student / source ref</th>
                            <th class="py-2 font-semibold">Date</th>
                            <th class="py-2 font-semibold">Amount</th>
                            <th class="py-2 font-semibold">Balance</th>
                            <th class="py-2 font-semibold">Status</th>
                            <th class="py-2 font-semibold">Flags</th>
                            <th class="py-2 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $row)
                            <tr class="border-b border-neutral-100 last:border-0 {{ $row->is_held ? 'opacity-60' : '' }}">
                                <td class="py-2.5">
                                    @if ($row->student)
                                        {{ $row->student->first_name }} {{ $row->student->last_name }}
                                    @else
                                        <span class="text-neutral-500">{{ $row->raw_student_key ?: '—' }}</span>
                                        <x-badge color="yellow">Unmatched</x-badge>
                                    @endif
                                </td>
                                <td class="py-2.5">{{ $row->txn_date->format('M j, Y') }}</td>
                                <td class="py-2.5">{{ number_format($row->amount) }}</td>
                                <td class="py-2.5">{{ number_format($row->balance) }}</td>
                                <td class="py-2.5">
                                    <x-badge :color="$row->status === 'Paid' ? 'green' : ($row->status === 'Partial' ? 'yellow' : 'red')">{{ $row->status }}</x-badge>
                                </td>
                                <td class="py-2.5 space-x-1">
                                    @if ($row->is_restricted)<x-badge color="blue">Restricted</x-badge>@endif
                                    @if ($row->is_held)<x-badge color="yellow">Held</x-badge>@endif
                                </td>
                                <td class="py-2.5 text-right whitespace-nowrap space-x-3">
                                    <form method="POST" action="{{ route('treasurer.validate.toggle-restrict', $row) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-[#2E5AAC] hover:underline">
                                            {{ $row->is_restricted ? 'Unrestrict' : 'Restrict' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('treasurer.validate.toggle-hold', $row) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-[#8A6D10] hover:underline">
                                            {{ $row->is_held ? 'Release' : 'Hold' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 text-neutral-400">No rows in this batch.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Publish">
            @if ($batch->is_published)
                <x-badge color="green">Published {{ $batch->published_at->format('M j, Y H:i') }}</x-badge>
            @elseif ($blockingCount > 0)
                <p class="text-sm text-[#B0392B] mb-2 font-semibold">{{ $blockingCount }} unmatched row(s) block publishing.</p>
                <p class="text-sm text-neutral-500">Match each row to a student, or put it on hold to park it out of this publish.</p>
            @else
                <p class="text-sm text-neutral-500 mb-4">
                    Publishing makes matched records visible to leadership (read-only) and guardians/students.
                    @if ($heldCount > 0) {{ $heldCount }} held row(s) will stay excluded until released. @endif
                </p>
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
