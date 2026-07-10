<x-app-layout title="Imported Fee Records" subtitle="Transaction lines and student summaries generated from published import batches." badge="Sun account, not Sun Plus" role="treasurer">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Student summaries" color="blue">{{ $stats['studentSummaries'] }}</x-stat-tile>
        <x-stat-tile label="Transaction lines">{{ $stats['transactionLines'] }}</x-stat-tile>
        <x-stat-tile label="Partial / outstanding" color="yellow">{{ $stats['partialOutstanding'] }}</x-stat-tile>
        <x-stat-tile label="Hidden (restricted) rows" color="pink">{{ $stats['hiddenRows'] }}</x-stat-tile>
    </div>

    <x-card>
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-semibold mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name or ID" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div class="w-56">
                <label class="block text-sm font-semibold mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All statuses</option>
                    @foreach (['Paid', 'Partial', 'Owed', 'Outstanding'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Filter</button>
        </form>
    </x-card>

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Student</th>
                    <th class="py-2 font-semibold">Total billed</th>
                    <th class="py-2 font-semibold">Paid</th>
                    <th class="py-2 font-semibold">Balance</th>
                    <th class="py-2 font-semibold">Status</th>
                    <th class="py-2 font-semibold">Restricted?</th>
                    <th class="py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaries as $summary)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $summary->student->first_name }} {{ $summary->student->last_name }}</td>
                        <td class="py-2.5">{{ number_format($summary->total_billed) }}</td>
                        <td class="py-2.5">{{ number_format($summary->paid) }}</td>
                        <td class="py-2.5">{{ number_format($summary->balance) }}</td>
                        <td class="py-2.5"><x-badge :color="$summary->status === 'Paid' ? 'green' : ($summary->status === 'Partial' ? 'yellow' : 'pink')">{{ $summary->status }}</x-badge></td>
                        <td class="py-2.5 text-neutral-500">{{ $summary->is_restricted ? 'Yes: SDA hidden' : 'No' }}</td>
                        <td class="py-2.5 text-right"><a href="{{ route('treasurer.records.show', $summary->student) }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-4 text-neutral-400">No matched fee records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Record rule">
        <p class="text-sm text-neutral-500">
            Guardians/students see amounts owed, paid, partial, and outstanding. SDA student discounts and allowances
            (restricted rows) are hidden from their view.
        </p>
        <div class="flex flex-wrap gap-2 mt-3">
            <x-badge color="blue">Treasurer: full import data</x-badge>
            <x-badge color="yellow">Guardian/student: restricted view</x-badge>
            <x-badge color="green">Principal / VP / Registrar: read-only</x-badge>
        </div>
    </x-card>
</x-app-layout>
