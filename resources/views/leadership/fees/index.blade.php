<x-app-layout title="Imported Fee Records" subtitle="Read-only visibility of Treasurer-imported fee records." badge="Read-only" :role="$role">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-stat-tile label="Outstanding total" color="yellow">{{ number_format($outstandingTotal) }}</x-stat-tile>
        <x-stat-tile label="Paid total" color="green">{{ number_format($paidTotal) }}</x-stat-tile>
    </div>

    <x-card title="Outstanding by department">
        @php $maxDept = $byDepartment->max('outstanding') ?: 1; @endphp
        <div class="space-y-3">
            @forelse ($byDepartment as $row)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span>{{ $row->department }}</span>
                        <span class="font-semibold">{{ number_format($row->outstanding) }}</span>
                    </div>
                    <div class="h-3 rounded-full bg-neutral-100 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $row->outstanding / $maxDept * 100 }}%; background:#2a78d6"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No outstanding balances.</p>
            @endforelse
        </div>
    </x-card>

    <x-card title="Student fee summaries">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Student</th>
                    <th class="py-2 font-semibold">Total billed</th>
                    <th class="py-2 font-semibold">Balance</th>
                    <th class="py-2 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaries as $summary)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $summary->student->name }}</td>
                        <td class="py-2.5">{{ number_format($summary->total_billed) }}</td>
                        <td class="py-2.5">{{ number_format($summary->balance) }}</td>
                        <td class="py-2.5"><x-badge :color="$summary->status === 'Paid' ? 'green' : ($summary->status === 'Partial' ? 'yellow' : 'pink')">{{ $summary->status }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No fee records imported yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
