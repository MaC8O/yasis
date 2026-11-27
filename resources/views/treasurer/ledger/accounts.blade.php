@use('App\Support\LedgerFormat')
<x-app-layout title="Ledger Accounts" subtitle="Every ledger account for a financial year, with its opening and closing balance." badge="Treasurer" role="treasurer">
    <x-card>
        <form method="GET" class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div>
                <label for="la-year" class="block text-sm font-semibold mb-1">Financial year</label>
                <select id="la-year" name="year" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                    @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $fy)>{{ LedgerFormat::year($y) }}</option>@endforeach
                </select>
            </div>
            <div class="flex-1">
                <label for="la-search" class="block text-sm font-semibold mb-1">Search</label>
                <input id="la-search" type="search" name="search" value="{{ $search }}" placeholder="Account code, printed name, student name or ID" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
            </div>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px]">Search</button>
        </form>
    </x-card>

    <x-card :title="number_format($balances->total()).' '.Str::plural('account', $balances->total())">
        @if ($balances->isEmpty())
            <p class="text-sm text-neutral-400">No accounts.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-accounts>
                    <thead><tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 pr-3 font-semibold">Account</th><th class="py-2 pr-3 font-semibold">Student</th>
                        <th class="py-2 pr-3 font-semibold text-right">Opening</th><th class="py-2 pr-3 font-semibold text-right">Closing</th><th class="py-2 font-semibold">Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($balances as $b)
                            @php $a = $b->account; @endphp
                            <tr class="border-b border-neutral-100">
                                <td class="py-2 pr-3"><a href="{{ route('treasurer.ledger.account', [$a, 'year' => $fy]) }}" class="font-semibold text-brand hover:underline tabular-nums">{{ $a->code }}</a>
                                    <span class="block text-xs text-neutral-500">{{ $b->account_name_as_printed }}</span></td>
                                <td class="py-2 pr-3">
                                    @if ($a->is_control) <x-badge color="blue">Student Control</x-badge>
                                    @elseif ($a->student) {{ $a->student->name }} <span class="block text-xs text-neutral-500">{{ $a->student->student_id_number }}</span>
                                    @elseif ($a->not_isms_student) <span class="text-neutral-500">Not an ISMS student</span>
                                    @else <x-badge color="yellow">Not matched</x-badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ LedgerFormat::money($b->opening_balance) }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap font-semibold">{{ LedgerFormat::money($b->closing_balance) }}</td>
                                <td class="py-2 whitespace-nowrap">{{ $a->is_control ? '—' : $b->status() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $balances->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
