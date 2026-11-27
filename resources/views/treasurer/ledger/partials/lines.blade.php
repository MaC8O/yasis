{{-- A short list of ledger lines for the Treasurer's review: $lines, $total, $fy, $empty. --}}
@use('App\Support\LedgerFormat')
@if ($lines->isEmpty())
    <p class="text-sm text-neutral-400">{{ $empty }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-neutral-500 border-b border-neutral-200">
                <th class="py-2 pr-3 font-semibold">Account</th><th class="py-2 pr-3 font-semibold">Period</th><th class="py-2 pr-3 font-semibold">Typed date</th>
                <th class="py-2 pr-3 font-semibold">Memo</th><th class="py-2 pr-3 font-semibold">Category</th><th class="py-2 font-semibold text-right">Amount</th>
            </tr></thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr class="border-b border-neutral-100">
                        <td class="py-2 pr-3 whitespace-nowrap"><a href="{{ route('treasurer.ledger.account', [$line->sun_account_id, 'year' => $line->fiscal_year]) }}" class="text-brand font-semibold hover:underline tabular-nums">{{ $line->sun_account_code }}</a></td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $line->period_month->format('M Y') }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap {{ $line->date_suspect ? 'text-warning font-semibold' : '' }}">{{ $line->txn_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="py-2 pr-3 min-w-[14rem]">{{ $line->memo }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ str_replace('_', ' ', $line->category) }}</td>
                        <td class="py-2 text-right tabular-nums whitespace-nowrap">{{ LedgerFormat::money($line->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($total > $lines->count())
        <p class="mt-2 text-xs text-neutral-500">Showing the first {{ $lines->count() }} of {{ number_format($total) }}.</p>
    @endif
@endif
