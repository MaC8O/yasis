@props(['lines', 'flagRestricted' => false])
@use('App\Support\Money')
{{--
    Statement of account (open-item style): one row per imported charge — what was billed, what
    has been paid against it, what is still open, and the running balance of open items.
--}}
@php
    $charges = $lines->sum('charge');
    $payments = $lines->sum('payment');
    $open = $lines->sum('open');
    $closing = $lines->last()?->balance ?? 0;
    $num = 'py-2 px-3 text-right tabular-nums whitespace-nowrap';
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b-2 border-neutral-300 bg-neutral-50">
                <th class="py-2 px-3 text-left font-semibold">Date</th>
                <th class="py-2 px-3 text-left font-semibold">Ref</th>
                <th class="py-2 px-3 text-left font-semibold">Description</th>
                <th class="py-2 px-3 text-right font-semibold">Charged</th>
                <th class="py-2 px-3 text-right font-semibold">Paid</th>
                <th class="py-2 px-3 text-right font-semibold">Open</th>
                <th class="py-2 px-3 text-right font-semibold">Balance ({{ Money::CURRENCY }})</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-neutral-100 text-neutral-500 italic">
                <td class="py-2 px-3" colspan="6">Opening balance</td>
                <td class="{{ $num }}">0</td>
            </tr>
            @forelse ($lines as $line)
                <tr class="border-b border-neutral-100 {{ $line->record->is_restricted && $flagRestricted ? 'bg-tint-purple/40' : '' }}">
                    <td class="py-2 px-3 whitespace-nowrap">{{ $line->record->txn_date->format('d M Y') }}</td>
                    <td class="py-2 px-3 text-neutral-500 whitespace-nowrap">{{ $line->record->importBatch?->period }}</td>
                    <td class="py-2 px-3">
                        {{ $line->record->description ?: 'Fee charge' }}
                        @if ($flagRestricted && $line->record->is_restricted)
                            <span class="ml-1 rounded bg-tint-purple text-tint-purple-ink px-1.5 py-0.5 text-[10px] font-bold uppercase">SDA · hidden from family</span>
                        @endif
                        @if ($flagRestricted && $line->record->is_held)
                            <span class="ml-1 rounded bg-tint-yellow text-tint-yellow-ink px-1.5 py-0.5 text-[10px] font-bold uppercase">Held</span>
                        @endif
                    </td>
                    <td class="{{ $num }}">{{ Money::format($line->charge, true) }}</td>
                    <td class="{{ $num }} text-success">{{ Money::format($line->payment, true) }}</td>
                    <td class="{{ $num }} {{ $line->open > 0 ? 'text-danger' : 'text-neutral-400' }}">{{ Money::format($line->open, true) }}</td>
                    <td class="{{ $num }} font-semibold">{{ Money::format($line->balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 px-3 text-center text-neutral-400">No fee lines to show.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-neutral-300 bg-neutral-50 font-bold">
                <td class="py-2.5 px-3" colspan="3">Closing balance</td>
                <td class="{{ $num }}">{{ Money::format($charges) }}</td>
                <td class="{{ $num }} text-success">{{ Money::format($payments) }}</td>
                <td class="{{ $num }} {{ $open > 0 ? 'text-danger' : '' }}">{{ Money::format($open) }}</td>
                <td class="{{ $num }} {{ $closing > 0 ? 'text-danger' : 'text-success' }}">{{ Money::format($closing) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
