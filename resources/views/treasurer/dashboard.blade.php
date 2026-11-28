@use('App\Support\LedgerFormat')
<x-app-layout title="Finance Dashboard" :subtitle="'The Sun ledger'.($yearLabel ? ' for '.$yearLabel : '').' and what is waiting for you. The ISMS never records payments.'" badge="Treasurer" role="treasurer">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Owed to the school (Ks)" color="yellow" :href="route('treasurer.reports.index')" :hint="$distribution['Owing'].' '.Str::plural('student', $distribution['Owing']).' owing'">{{ number_format($totals['owing']) }}</x-stat-tile>
        <x-stat-tile label="In credit (Ks)" color="green" :hint="$distribution['In credit'].' paid in advance or overpaid'">{{ number_format($totals['credit']) }}</x-stat-tile>
        <x-stat-tile label="Accounts to match" color="blue" :href="route('treasurer.ledger.review', array_filter(['year' => $fy]))">{{ $unmatched }}</x-stat-tile>
        <x-stat-tile label="Possible reused codes" color="pink" :href="route('treasurer.ledger.review', array_filter(['year' => $fy]))" hint="held until you confirm">{{ $pendingReviews }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Updating the ledger" subtitle="Print the Account Listing from Sun, import it, review it, then publish.">
            <ol class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                @foreach (['Print from Sun', 'Import', 'Review and match', 'Publish', 'Families see it'] as $step)
                    <li class="flex items-center gap-2">
                        <span class="rounded-full bg-brand-soft text-brand px-2.5 py-1">{{ $step }}</span>
                        @unless ($loop->last)<x-icon name="chevron-right" class="w-3.5 h-3.5 text-neutral-400" />@endunless
                    </li>
                @endforeach
            </ol>
            <div class="flex flex-wrap gap-2 mt-4">
                <a href="{{ route('treasurer.info.source-prep') }}" class="inline-flex items-center min-h-[40px] border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2 text-sm hover:bg-neutral-50">How to print the listing</a>
                <a href="{{ route('treasurer.ledger.index') }}" class="inline-flex items-center min-h-[40px] bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark">Import a listing</a>
            </div>
        </x-card>

        <x-card title="Waiting for you">
            <ul class="divide-y divide-neutral-100 text-sm" data-todo>
                @foreach ([
                    ['Ledger accounts to match to a student', $unmatched, route('treasurer.ledger.review', array_filter(['year' => $fy]))],
                    ['Possible reused codes to confirm', $pendingReviews, route('treasurer.ledger.review', array_filter(['year' => $fy]))],
                    ['Imports not yet published', $unpublished, route('treasurer.ledger.index')],
                ] as [$label, $count, $href])
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <a href="{{ $href }}" class="text-ink hover:underline">{{ $label }}</a>
                        <span class="font-bold tabular-nums {{ $count ? 'text-warning' : 'text-success' }}">{{ $count }}</span>
                    </li>
                @endforeach
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span>Student Control balance{{ $yearLabel ? ', '.$yearLabel : '' }}</span>
                    <span class="font-bold tabular-nums">{{ $control !== null ? LedgerFormat::money($control) : '—' }}</span>
                </li>
            </ul>
        </x-card>
    </div>

    <x-card title="Recent imports">
        @if ($recentBatches->isEmpty())
            <p class="text-sm text-neutral-400">Nothing imported yet.</p>
        @else
            <ul class="divide-y divide-neutral-100 text-sm">
                @foreach ($recentBatches as $batch)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <span class="min-w-0"><span class="font-medium break-words">{{ $batch->source_file }}</span>
                            <span class="block text-xs text-neutral-500">{{ $batch->yearLabel() }} · {{ number_format($batch->lines_count) }} lines · imported {{ $batch->created_at->format('j M Y') }}</span></span>
                        <x-badge :color="$batch->isPublished() ? 'green' : 'yellow'">{{ $batch->isPublished() ? 'Published' : 'Not published' }}</x-badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
