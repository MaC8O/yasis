<x-app-layout title="Finance Dashboard" subtitle="Overview of imported fee records, matching status, reporting, and visibility rules." badge="Sun account, not Sun Plus" role="treasurer">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Upload cycle" color="blue">Quarterly / demand</x-stat-tile>
        <x-stat-tile label="Matched records" color="green">{{ $matchedRows }} / {{ $totalRows }}</x-stat-tile>
        <x-stat-tile label="Need review" color="yellow">{{ $needsReview }}</x-stat-tile>
        <x-stat-tile label="Visible users">4 roles</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Finance office reality" subtitle="Built from the questionnaire answers.">
            <div class="flex flex-wrap gap-2">
                <x-badge color="yellow">Uses Sun account only</x-badge>
                <x-badge color="blue">Excel export, Word adjustment possible</x-badge>
                <x-badge color="green">Installment payments: quarterly / on demand</x-badge>
            </div>
            <a href="{{ route('treasurer.info.source-prep') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Prepare source file</a>
        </x-card>

        <x-card title="Operational queue" subtitle="Tasks to work through.">
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span>Unmatched rows</span><span class="font-semibold">{{ $needsReview }}</span></div>
            </div>
            <a href="{{ route('treasurer.validate.index') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Validate import</a>
            <a href="{{ route('treasurer.reports.index') }}" class="inline-block mt-4 ml-2 text-sm font-semibold text-[#1F573D] hover:underline">Reports</a>
        </x-card>
    </div>

    <x-card title="Recent batches" subtitle="Every import is kept as an auditable batch.">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Batch</th>
                    <th class="py-2 font-semibold">Rows</th>
                    <th class="py-2 font-semibold">Matched</th>
                    <th class="py-2 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentBatches as $batch)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $batch->period }}</td>
                        <td class="py-2.5">{{ $batch->importedFeeRecords_count }}</td>
                        <td class="py-2.5">{{ $batch->matched_count }}</td>
                        <td class="py-2.5"><x-badge :color="$batch->is_published ? 'green' : 'yellow'">{{ $batch->is_published ? 'Published' : 'Review' }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No batches uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Collection rate by period" subtitle="Collected share of billed amounts per import period.">
        <x-chart.bar-list
            :items="collect($byPeriod)->map(fn ($row) => ['label' => $row->period, 'value' => $row->rate, 'display' => $row->rate.'%'])"
            :max="100" label-width="w-24" />
    </x-card>
</x-app-layout>
