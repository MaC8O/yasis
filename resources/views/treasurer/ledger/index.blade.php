@use('App\Support\LedgerFormat')
<x-app-layout title="Ledger Import" subtitle="Import the Account Listing printed from Sun Accounts, as it comes out of Sun. The ISMS never records payments." badge="Treasurer" role="treasurer">
    <x-card title="Upload an Account Listing" subtitle="Print the Account Listing report for the student accounts to a file (.PRN) and upload it here. You'll see a summary before anything is saved.">
        <form method="POST" action="{{ route('treasurer.ledger.upload') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row sm:items-end gap-4">
            @csrf
            <div class="flex-1">
                <label for="ledger-file" class="block text-sm font-semibold mb-1">File</label>
                <input id="ledger-file" type="file" name="file" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-4 file:py-2.5 file:font-semibold file:text-brand">
                @error('file')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Check the file</button>
        </form>
        <p class="mt-3 text-xs text-neutral-500">The file is kept privately and only the Treasurer and the Admin can download it. An upload you don't import is deleted after an hour.</p>
    </x-card>

    <x-card title="Imports" subtitle="Families see an import's lines only once it is published. Revert undoes exactly what an import added or changed; only the latest import to change a line can be reverted.">
        @if ($batches->isEmpty())
            <p class="text-sm text-neutral-400">Nothing imported yet.</p>
        @else
            {{-- Phones: one card per import. --}}
            <div class="sm:hidden divide-y divide-neutral-100" data-batch-cards>
                @foreach ($batches as $batch)
                    <div class="py-3 text-sm space-y-1">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold break-words min-w-0">#{{ $batch->id }} · {{ $batch->source_file }}</p>
                            @if ($batch->isReverted())
                                <x-badge color="red">Reverted</x-badge>
                            @elseif ($batch->isPublished())
                                <x-badge color="green">Published</x-badge>
                            @else
                                <x-badge color="yellow">Not published</x-badge>
                            @endif
                        </div>
                        <p class="text-xs text-neutral-500">{{ $batch->yearLabel() }} · printed {{ $batch->printed_at ?? '—' }} · imported {{ $batch->created_at->format('j M Y H:i') }}</p>
                        <p class="text-xs">{{ number_format($batch->lines_count) }} lines ({{ $batch->new_lines }} new, {{ $batch->updated_lines }} changed{{ $batch->missing_lines ? ', '.$batch->missing_lines.' missing' : '' }}) · {{ number_format($batch->accounts_count) }} accounts · Student Control {{ LedgerFormat::money($batch->control_balance) }}</p>
                        @include('treasurer.ledger.partials.batch-actions', ['batch' => $batch, 'align' => 'justify-start'])
                    </div>
                @endforeach
            </div>
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-sm" data-batches>
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 pr-3 font-semibold">#</th>
                            <th class="py-2 pr-3 font-semibold">File</th>
                            <th class="py-2 pr-3 font-semibold">Year</th>
                            <th class="py-2 pr-3 font-semibold text-right">Lines</th>
                            <th class="py-2 pr-3 font-semibold text-right">Accounts</th>
                            <th class="py-2 pr-3 font-semibold text-right">Student Control</th>
                            <th class="py-2 pr-3 font-semibold">Status</th>
                            <th class="py-2 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            <tr class="border-b border-neutral-100 align-top" data-batch="{{ $batch->id }}">
                                <td class="py-2.5 pr-3 tabular-nums">{{ $batch->id }}</td>
                                <td class="py-2.5 pr-3">
                                    <span class="font-medium break-all">{{ $batch->source_file }}</span>
                                    <span class="block text-xs text-neutral-500">Printed {{ $batch->printed_at ?? '—' }} · imported {{ $batch->created_at->format('j M Y H:i') }} by {{ $batch->uploadedBy?->name }}</span>
                                </td>
                                <td class="py-2.5 pr-3 whitespace-nowrap">{{ $batch->yearLabel() }}</td>
                                <td class="py-2.5 pr-3 text-right tabular-nums whitespace-nowrap">{{ number_format($batch->lines_count) }}
                                    <span class="block text-xs text-neutral-500">{{ $batch->new_lines }} new · {{ $batch->updated_lines }} changed{{ $batch->missing_lines ? ' · '.$batch->missing_lines.' missing' : '' }}</span>
                                </td>
                                <td class="py-2.5 pr-3 text-right tabular-nums whitespace-nowrap">{{ number_format($batch->accounts_count) }}
                                    <span class="block text-xs text-neutral-500">{{ $batch->unmatched_accounts }} unmatched at import</span>
                                </td>
                                <td class="py-2.5 pr-3 text-right tabular-nums whitespace-nowrap">{{ LedgerFormat::money($batch->control_balance) }}</td>
                                <td class="py-2.5 pr-3">
                                    @if ($batch->isReverted())
                                        <x-badge color="red">Reverted</x-badge>
                                    @elseif ($batch->isPublished())
                                        <x-badge color="green">Published</x-badge>
                                    @else
                                        <x-badge color="yellow">Not published</x-badge>
                                    @endif
                                </td>
                                <td class="py-2.5 text-right">
                                    @include('treasurer.ledger.partials.batch-actions', ['batch' => $batch])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-app-layout>
