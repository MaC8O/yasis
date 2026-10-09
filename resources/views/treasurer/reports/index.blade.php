@use('App\Support\Money')
<x-app-layout title="Fee Reports" subtitle="Receivables, aging and collection reports built from imported fee records." badge="Treasurer" role="treasurer">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Total billed ({{ Money::CURRENCY }})" color="blue">{{ Money::format($billedTotal) }}</x-stat-tile>
        <x-stat-tile label="Collected ({{ Money::CURRENCY }})" color="green">{{ Money::format($paidTotal) }}</x-stat-tile>
        <x-stat-tile label="Receivables ({{ Money::CURRENCY }})" color="yellow">{{ Money::format($outstandingTotal) }}</x-stat-tile>
        <x-stat-tile label="Accounts with balance" color="pink" :href="route('treasurer.records.index', ['sort' => 'balance'])">{{ $studentsWithBalance }}</x-stat-tile>
    </div>

    {{-- Report pack --}}
    <x-card title="Report pack" subtitle="Downloads open in Excel; Myanmar names are preserved. Every report you generate is recorded in the audit log.">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach ([
                ['route' => route('treasurer.reports.outstanding'), 'icon' => 'list', 'title' => 'Outstanding balances', 'desc' => 'Every account that owes money, largest first — with full name, class and guardian contact for follow-up.', 'kind' => 'CSV'],
                ['route' => route('treasurer.reports.aging'), 'icon' => 'clock', 'title' => 'Aged receivables', 'desc' => 'Each balance split into 0–30, 31–60, 61–90 and 90+ days, with totals.', 'kind' => 'CSV'],
                ['route' => route('treasurer.records.index'), 'icon' => 'document', 'title' => 'Statement of account', 'desc' => 'Open a student, then print the office copy or the family copy (SDA lines removed).', 'kind' => 'PDF'],
            ] as $report)
                <a href="{{ $report['route'] }}" class="group flex gap-3 rounded-xl border border-neutral-200 p-4 hover:border-brand hover:bg-brand-soft/40 transition-colors">
                    <span class="w-10 h-10 shrink-0 rounded-lg bg-brand-soft text-brand flex items-center justify-center"><x-icon :name="$report['icon']" /></span>
                    <span class="min-w-0">
                        <span class="flex items-center gap-2 font-semibold text-ink">{{ $report['title'] }}
                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-bold text-neutral-600">{{ $report['kind'] }}</span>
                        </span>
                        <span class="block text-xs text-neutral-500 mt-0.5">{{ $report['desc'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </x-card>

    <x-card title="Aged receivables" subtitle="All open balances by how long they have been unpaid.">
        <x-finance.aging-strip :aging="$aging" />
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Fee status distribution" subtitle="Share of total billed amount, by account status.">
            <x-chart.donut
                :segments="[
                    ['label' => 'Paid', 'value' => $distribution['paid'], 'color' => '#1F573D'],
                    ['label' => 'Partial', 'value' => $distribution['partial'], 'color' => '#A8841B'],
                    ['label' => 'Outstanding', 'value' => $distribution['outstanding'], 'color' => '#B0392B'],
                ]"
                :center="$distributionPct['paid'].'%'"
                center-label="paid" />
        </x-card>

        <x-card title="Receivables by department" subtitle="Open balance and number of accounts owing.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 text-left font-semibold">Department</th>
                        <th class="py-2 text-right font-semibold">Accounts</th>
                        <th class="py-2 text-right font-semibold">Balance ({{ Money::CURRENCY }})</th>
                        <th class="py-2 pl-3 text-right font-semibold w-24">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byDepartment as $row)
                        <tr class="border-b border-neutral-100">
                            <td class="py-2">{{ $row->department }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $row->students }}</td>
                            <td class="py-2 text-right tabular-nums font-semibold">{{ Money::format($row->outstanding) }}</td>
                            <td class="py-2 pl-3 text-right tabular-nums text-neutral-500">{{ $outstandingTotal > 0 ? round($row->outstanding / $outstandingTotal * 100) : 0 }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-neutral-400">No open balances.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Largest balances" subtitle="Top 10 accounts for follow-up.">
            <table class="w-full text-sm">
                <tbody>
                    @forelse ($topDebtors as $i => $row)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2 w-6 text-neutral-400 tabular-nums">{{ $i + 1 }}</td>
                            <td class="py-2">
                                <a href="{{ route('treasurer.records.show', $row->student) }}" class="font-medium text-ink hover:underline">{{ $row->student->name }}</a>
                                <span class="text-xs text-neutral-400 tabular-nums">· {{ $row->student->student_id_number }}</span>
                            </td>
                            <td class="py-2 text-right tabular-nums font-semibold">{{ Money::format($row->balance) }}</td>
                            <td class="py-2 pl-3 text-right"><x-finance.status-pill :status="$row->status" /></td>
                        </tr>
                    @empty
                        <tr><td class="py-4 text-neutral-400">No open balances.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card title="Collection rate by period" subtitle="Collected share of billed amounts per import period.">
            <x-chart.bar-list
                :items="collect($byPeriod)->map(fn ($row) => ['label' => $row->period, 'value' => $row->rate, 'display' => $row->rate.'%'])"
                :max="100" label-width="w-24" />
        </x-card>
    </div>

    <p class="text-xs text-neutral-400">
        Office reports include every imported line. Leadership and guardians see these figures through their own
        <a href="{{ route('treasurer.info.visibility-rules') }}" class="font-semibold text-brand hover:underline">visibility rules</a>.
    </p>
</x-app-layout>
