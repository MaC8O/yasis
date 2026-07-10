<x-app-layout :title="$student->name" :subtitle="'Fee detail — '.$student->student_id_number" badge="Sun account, not Sun Plus" role="treasurer">
    <a href="{{ route('treasurer.reports.statement', $student) }}" target="_blank" class="inline-block bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">
        Download fee statement (PDF)
    </a>

    <x-card title="Transaction lines">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Date</th>
                    <th class="py-2 font-semibold">Batch / Period</th>
                    <th class="py-2 font-semibold">Amount</th>
                    <th class="py-2 font-semibold">Balance</th>
                    <th class="py-2 font-semibold">Status</th>
                    <th class="py-2 font-semibold">Restricted</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $record->txn_date->format('M j, Y') }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $record->importBatch->period }}</td>
                        <td class="py-2.5">{{ number_format($record->amount) }}</td>
                        <td class="py-2.5">{{ number_format($record->balance) }}</td>
                        <td class="py-2.5"><x-badge :color="$record->status === 'Paid' ? 'green' : ($record->status === 'Partial' ? 'yellow' : 'pink')">{{ $record->status }}</x-badge></td>
                        <td class="py-2.5">{{ $record->is_restricted ? 'SDA hidden' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-neutral-400">No fee records for this student.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <a href="{{ route('treasurer.records.index') }}" class="inline-block text-sm font-semibold text-neutral-500 hover:underline">Back to records</a>
</x-app-layout>
