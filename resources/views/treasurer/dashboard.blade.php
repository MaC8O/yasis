@use('App\Support\Money')
<x-app-layout title="Finance Dashboard" subtitle="Receivables, collection and the import pipeline at a glance." badge="Sun account, not Sun Plus" role="treasurer">
    <div class="flex flex-wrap gap-2 -mt-2">
        <x-badge color="yellow">Sun account, not Sun Plus</x-badge>
        <x-badge color="neutral">No transactions in ISMS</x-badge>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Receivables ({{ Money::CURRENCY }})" color="yellow" :href="route('treasurer.records.index', ['sort' => 'balance'])" :hint="$accountsWithBalance.' '.Str::plural('account', $accountsWithBalance).' owing'">{{ Money::format($receivables) }}</x-stat-tile>
        <x-stat-tile label="Collected ({{ Money::CURRENCY }})" color="green" :hint="$collectionRate !== null ? $collectionRate.'% of billed' : null">{{ Money::format($collected) }}</x-stat-tile>
        <x-stat-tile label="Over 90 days ({{ Money::CURRENCY }})" color="pink" :href="route('treasurer.records.index', ['aging' => 'overdue', 'sort' => 'overdue'])">{{ Money::format($aging['over_90']) }}</x-stat-tile>
        <x-stat-tile label="Matched records" color="blue" :href="route('treasurer.validate.index')" :hint="$needsReview ? $needsReview.' need review' : 'All matched'">{{ $matchedRows }} / {{ $totalRows }}</x-stat-tile>
    </div>

    <x-card title="Aged receivables" subtitle="Open balances by how long they have been unpaid.">
        <x-finance.aging-strip :aging="$aging" />
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Import pipeline" subtitle="From the finalized Sun account to the family portal.">
            <ol class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                @foreach (['Sun finalized', 'Export', 'Correct', 'Upload', 'Validate', 'Publish', 'Portal'] as $i => $step)
                    <li class="flex items-center gap-2">
                        <span class="rounded-full bg-brand-soft text-brand px-2.5 py-1">{{ $step }}</span>
                        @unless ($loop->last)<x-icon name="chevron-right" class="w-3.5 h-3.5 text-neutral-400" />@endunless
                    </li>
                @endforeach
            </ol>
            <div class="flex flex-wrap gap-2 mt-4">
                <a href="{{ route('treasurer.info.source-prep') }}" class="border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2 text-sm hover:bg-neutral-50">Prepare source file</a>
                <a href="{{ route('treasurer.import.index') }}" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">Import records</a>
            </div>
        </x-card>

        <x-card title="Operational queue" subtitle="What needs your attention.">
            @php
                $queue = [
                    ['label' => 'Unmatched rows to resolve', 'count' => $needsReview, 'href' => route('treasurer.validate.index'), 'action' => 'Validate'],
                    ['label' => 'Draft batches not yet published', 'count' => $draftBatches, 'href' => route('treasurer.history.index'), 'action' => 'Review'],
                    ['label' => 'Batches with unconfirmed SDA rows', 'count' => $unconfirmedBatches, 'href' => route('treasurer.info.visibility-rules'), 'action' => 'Confirm'],
                    ['label' => 'Restricted (SDA) rows on file', 'count' => $restrictedRows, 'href' => route('treasurer.info.visibility-rules'), 'action' => 'Rules'],
                ];
            @endphp
            <ul class="divide-y divide-neutral-100 text-sm -my-2">
                @foreach ($queue as $item)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="flex items-center gap-2">
                            <span class="min-w-7 text-center rounded-md px-1.5 py-0.5 text-xs font-bold tabular-nums {{ $item['count'] ? 'bg-warning-soft text-warning' : 'bg-neutral-100 text-neutral-400' }}">{{ $item['count'] }}</span>
                            {{ $item['label'] }}
                        </span>
                        <a href="{{ $item['href'] }}" class="text-xs font-semibold text-brand hover:underline">{{ $item['action'] }} →</a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-200 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-ink">Recent batches</h2>
                <p class="text-sm text-neutral-500">Every import is kept as an auditable batch.</p>
            </div>
            <a href="{{ route('treasurer.history.index') }}" class="text-sm font-semibold text-brand hover:underline">All batches →</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200 bg-neutral-50">
                    <th class="py-2 pl-5 pr-2 text-left font-semibold">Batch</th>
                    <th class="py-2 px-2 text-right font-semibold">Rows</th>
                    <th class="py-2 px-2 text-right font-semibold">Matched</th>
                    <th class="py-2 px-2 text-right font-semibold">Billed ({{ Money::CURRENCY }})</th>
                    <th class="py-2 pl-2 pr-5 text-left font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentBatches as $batch)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5 pl-5 pr-2"><a href="{{ route('treasurer.validate.index', ['batch' => $batch->id]) }}" class="font-medium text-ink hover:underline">{{ $batch->period }}</a> <span class="text-xs text-neutral-400">#{{ $batch->id }}</span></td>
                        <td class="py-2.5 px-2 text-right tabular-nums">{{ $batch->imported_fee_records_count }}</td>
                        <td class="py-2.5 px-2 text-right tabular-nums">{{ $batch->matched_count }}</td>
                        <td class="py-2.5 px-2 text-right tabular-nums">{{ Money::format($batch->billed_sum) }}</td>
                        <td class="py-2.5 pl-2 pr-5"><x-badge :color="$batch->is_published ? 'green' : 'yellow'" class="!px-2.5 !py-0.5 !text-xs">{{ $batch->is_published ? 'Published' : 'Draft' }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 px-5 text-neutral-400">No batches uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-card title="Collection rate by period" subtitle="Collected share of billed amounts per import period.">
        <x-chart.bar-list
            :items="collect($byPeriod)->map(fn ($row) => ['label' => $row->period, 'value' => $row->rate, 'display' => $row->rate.'%'])"
            :max="100" label-width="w-24" />
    </x-card>
</x-app-layout>
