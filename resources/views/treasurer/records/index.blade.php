@use('App\Support\Money')
<x-app-layout title="Accounts Receivable" subtitle="Every student's fee account, from imported records — balances, aging, and drill-down statements." badge="Sun account, not Sun Plus" role="treasurer">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Total receivables ({{ Money::CURRENCY }})" color="yellow">{{ Money::format($stats['receivables']) }}</x-stat-tile>
        <x-stat-tile label="Accounts with a balance" color="blue">{{ $stats['accounts'] }}</x-stat-tile>
        <x-stat-tile label="Overdue > 30 days ({{ Money::CURRENCY }})" color="pink" :href="route('treasurer.records.index', ['aging' => 'overdue', 'sort' => 'overdue'])">{{ Money::format($stats['overdue']) }}</x-stat-tile>
        <x-stat-tile label="Restricted (SDA) rows" color="purple" :href="route('treasurer.info.visibility-rules')">{{ $stats['restrictedRows'] }}</x-stat-tile>
    </div>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        {{-- Toolbar --}}
        <form method="GET" class="flex flex-wrap items-end gap-3 px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Search</label>
                <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name or ID"
                       class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="status" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Status</label>
                <select id="status" name="status" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach (['Paid', 'Partial', 'Outstanding'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="aging" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Aging</label>
                <select id="aging" name="aging" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="">All accounts</option>
                    <option value="overdue" @selected(($filters['aging'] ?? '') === 'overdue')>Overdue (31+ days)</option>
                </select>
            </div>
            <div>
                <label for="sort" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Sort by</label>
                <select id="sort" name="sort" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                    <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Name</option>
                    <option value="balance" @selected(($filters['sort'] ?? '') === 'balance')>Largest balance</option>
                    <option value="overdue" @selected(($filters['sort'] ?? '') === 'overdue')>Most overdue</option>
                </select>
            </div>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">Apply</button>
            <a href="{{ route('treasurer.records.index') }}" class="text-sm font-semibold text-neutral-500 hover:underline py-2">Reset</a>
            <div class="flex-1 hidden xl:block"></div>
            <a href="{{ route('treasurer.reports.aging') }}" class="border border-neutral-300 bg-white text-neutral-700 font-semibold rounded-lg px-4 py-2 text-sm hover:bg-neutral-50">Export aging (CSV)</a>
        </form>

        {{-- Ledger --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b-2 border-neutral-300">
                        <th class="py-2 pl-4 pr-2 text-left font-semibold">Student</th>
                        <th class="py-2 px-2 text-left font-semibold">Class</th>
                        <th class="py-2 px-2 text-right font-semibold">Billed</th>
                        <th class="py-2 px-2 text-right font-semibold">Paid</th>
                        <th class="py-2 px-2 text-right font-semibold">Balance</th>
                        <th class="py-2 px-2 text-right font-semibold">0–30</th>
                        <th class="py-2 px-2 text-right font-semibold">31–60</th>
                        <th class="py-2 px-2 text-right font-semibold">61–90</th>
                        <th class="py-2 px-2 text-right font-semibold">90+</th>
                        <th class="py-2 pl-2 pr-4 text-left font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        <tr class="border-b border-neutral-100 hover:bg-neutral-50 cursor-pointer"
                            onclick="window.location='{{ route('treasurer.records.show', $summary->student) }}'">
                            <td class="py-2 pl-4 pr-2">
                                <a href="{{ route('treasurer.records.show', $summary->student) }}" class="font-medium text-ink hover:underline">{{ $summary->student->name }}</a>
                                <p class="text-xs text-neutral-400 tabular-nums">
                                    {{ $summary->student->student_id_number }}
                                    @if ($summary->is_restricted)<span class="ml-1 text-tint-purple-ink font-semibold">· SDA</span>@endif
                                </p>
                            </td>
                            <td class="py-2 px-2 text-neutral-500 whitespace-nowrap">{{ $summary->section?->name ?? $summary->student->department?->name }}</td>
                            <td class="py-2 px-2 text-right tabular-nums">{{ Money::format($summary->total_billed) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums text-success">{{ Money::format($summary->paid, true) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums font-bold {{ $summary->balance > 0 ? 'text-ink' : 'text-neutral-400' }}">{{ Money::format($summary->balance, true) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums text-neutral-600">{{ Money::format($summary->aging['current'], true) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums {{ $summary->aging['days_31_60'] > 0 ? 'text-warning font-semibold' : 'text-neutral-400' }}">{{ Money::format($summary->aging['days_31_60'], true) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums {{ $summary->aging['days_61_90'] > 0 ? 'text-[#9a4a1c] font-semibold' : 'text-neutral-400' }}">{{ Money::format($summary->aging['days_61_90'], true) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums {{ $summary->aging['over_90'] > 0 ? 'text-danger font-bold' : 'text-neutral-400' }}">{{ Money::format($summary->aging['over_90'], true) }}</td>
                            <td class="py-2 pl-2 pr-4"><x-finance.status-pill :status="$summary->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-8 px-4 text-center text-neutral-400">No accounts match these filters.</td></tr>
                    @endforelse
                </tbody>
                @if ($summaries->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-neutral-300 bg-neutral-50 font-bold tabular-nums">
                            <td class="py-2.5 pl-4 pr-2" colspan="2">Total · {{ $summaries->count() }} {{ Str::plural('account', $summaries->count()) }}</td>
                            <td class="py-2.5 px-2 text-right">{{ Money::format($totals['billed']) }}</td>
                            <td class="py-2.5 px-2 text-right text-success">{{ Money::format($totals['paid']) }}</td>
                            <td class="py-2.5 px-2 text-right">{{ Money::format($totals['balance']) }}</td>
                            <td class="py-2.5 px-2 text-right">{{ Money::format($totals['aging']['current'], true) }}</td>
                            <td class="py-2.5 px-2 text-right">{{ Money::format($totals['aging']['days_31_60'], true) }}</td>
                            <td class="py-2.5 px-2 text-right">{{ Money::format($totals['aging']['days_61_90'], true) }}</td>
                            <td class="py-2.5 px-2 text-right text-danger">{{ Money::format($totals['aging']['over_90'], true) }}</td>
                            <td class="py-2.5 pl-2 pr-4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs text-neutral-500">
            Amounts in {{ Money::CURRENCY }}. Aging counts each unpaid charge from the date it was billed. Records are imported from the accounting system — corrections are made there and re-uploaded.
        </p>
    </div>
</x-app-layout>
