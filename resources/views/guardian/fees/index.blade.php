<x-app-layout title="Fee Status" subtitle="View imported fee records from the school finance process." badge="Sun account, not Sun Plus" role="guardian">
    <x-child-switcher :children="$children" :child="$child" route="guardian.fees.index" />

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Total billed" color="blue">{{ number_format($totalBilled) }}</x-stat-tile>
        <x-stat-tile label="Paid" color="green">{{ number_format($paid) }}</x-stat-tile>
        <x-stat-tile label="Outstanding" color="yellow">{{ number_format($balance) }}</x-stat-tile>
        <x-stat-tile label="Status" color="blue">{{ $status }}</x-stat-tile>
    </div>

    <x-card title="Guardian statement" subtitle="Imported transaction lines, with restricted items hidden.">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Date</th>
                    <th class="py-2 font-semibold">Amount</th>
                    <th class="py-2 font-semibold">Balance</th>
                    <th class="py-2 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $record->txn_date->format('Y-m-d') }}</td>
                        <td class="py-2.5">{{ number_format($record->amount) }}</td>
                        <td class="py-2.5">{{ number_format($record->balance) }}</td>
                        <td class="py-2.5"><x-badge :color="$record->status === 'Paid' ? 'green' : ($record->status === 'Partial' ? 'yellow' : 'pink')">{{ $record->status }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No fee records available yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex items-center gap-3 mt-4">
            <x-badge color="blue">SDA discount / allowance hidden</x-badge>
            <a href="{{ route('guardian.fees.statement', ['child' => $child->id]) }}" target="_blank" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Download / Print Statement</a>
        </div>
    </x-card>

    <x-card title="Finance note">
        <p class="text-sm text-neutral-500">
            The portal displays imported records only. Corrections are handled by the finance office's source
            process and appear after the next upload.
        </p>
    </x-card>
</x-app-layout>
