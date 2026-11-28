@use('App\Support\LedgerFormat')
<x-app-layout title="Fee Reports" subtitle="From the live Sun ledger, published or not. Families see only published statements." badge="Treasurer" role="treasurer">
    @php $download = 'inline-flex items-center gap-1.5 min-h-[40px] border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-3 py-2 text-xs hover:bg-neutral-50'; @endphp
    @if ($years->isEmpty())
        <x-card><p class="text-sm text-neutral-400">Nothing imported yet. Start on <a href="{{ route('treasurer.ledger.index') }}" class="text-brand font-semibold hover:underline">Ledger Import</a>.</p></x-card>
    @else
        <x-card>
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="fr-year" class="block text-sm font-semibold mb-1">Financial year</label>
                    <select id="fr-year" name="year" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                        @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $fy)>{{ LedgerFormat::year($y) }}</option>@endforeach
                    </select>
                </div>
                <a href="{{ route('treasurer.reports.outstanding', ['year' => $fy]) }}" class="{{ $download }}"><x-icon name="download" class="w-4 h-4" /> Students owing (CSV)</a>
            </form>
        </x-card>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" data-report-tiles>
            <x-stat-tile label="Owing" color="yellow" :hint="number_format($totals['owing']).' Ks'">{{ $distribution['Owing'] }}</x-stat-tile>
            <x-stat-tile label="Settled" color="green" hint="balance 0">{{ $distribution['Settled'] }}</x-stat-tile>
            <x-stat-tile label="In credit" color="blue" :hint="number_format($totals['credit']).' Ks'">{{ $distribution['In credit'] }}</x-stat-tile>
            <x-stat-tile label="Matched students" color="pink">{{ array_sum($distribution) }}</x-stat-tile>
        </div>

        @foreach (['By grade (class this year)' => $byGrade, 'By department' => $byDepartment] as $title => $groups)
            <x-card :title="$title">
                @if ($groups->isEmpty())
                    <p class="text-sm text-neutral-400">No matched accounts this year.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-neutral-500 border-b border-neutral-200">
                                <th class="py-2 pr-3 font-semibold"></th><th class="py-2 pr-3 font-semibold text-right">Owing</th><th class="py-2 pr-3 font-semibold text-right">Owed (Ks)</th>
                                <th class="py-2 pr-3 font-semibold text-right">Settled</th><th class="py-2 pr-3 font-semibold text-right">In credit</th><th class="py-2 font-semibold text-right">Credit (Ks)</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($groups as $g)
                                    <tr class="border-b border-neutral-100">
                                        <td class="py-2 pr-3 font-medium whitespace-nowrap">{{ $g->name }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">{{ $g->owing }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">{{ number_format($g->owing_total) }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">{{ $g->settled }}</td>
                                        <td class="py-2 pr-3 text-right tabular-nums">{{ $g->credit }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ number_format($g->credit_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endforeach

        <x-card title="Charges, payments and allowances by month" subtitle="Student accounts only (Student Control is left out). Months come from the accounting period, not the typed date.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" data-by-month>
                    <thead><tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 pr-3 font-semibold">Month</th><th class="py-2 pr-3 font-semibold text-right">Charged</th><th class="py-2 pr-3 font-semibold text-right">Payments</th>
                        <th class="py-2 pr-3 font-semibold text-right">Allowances</th><th class="py-2 font-semibold text-right">Other credits</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($byMonth as $m)
                            <tr class="border-b border-neutral-100">
                                <td class="py-2 pr-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($m->period_month)->format('M Y') }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ number_format((float) $m->charges) }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ number_format((float) $m->payments) }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ number_format((float) $m->allowances) }}</td>
                                <td class="py-2 text-right tabular-nums">{{ number_format((float) $m->other_credits) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="By category">
                <ul class="divide-y divide-neutral-100 text-sm" data-by-category>
                    @foreach ($byCategory as $c)
                        <li class="flex justify-between gap-3 py-2">
                            <span>{{ ucfirst(str_replace('_', ' ', $c->category)) }} <span class="text-xs text-neutral-500">· {{ number_format($c->line_count) }} {{ Str::plural('line', $c->line_count) }}</span></span>
                            <span class="tabular-nums text-right">{{ number_format((float) $c->debits) }} charged<span class="block text-xs text-neutral-500">{{ number_format((float) $c->credits) }} credited</span></span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
            <x-card title="Student Control balance at each import" subtitle="It carries charges as well as receipts that aren't on a student's own account (T7).">
                <ul class="divide-y divide-neutral-100 text-sm" data-control-history>
                    @forelse ($control as $b)
                        <li class="flex justify-between gap-3 py-2">
                            <span class="min-w-0">#{{ $b->id }} · {{ LedgerFormat::year($b->fiscal_year) }}<span class="block text-xs text-neutral-500">{{ $b->created_at->format('j M Y') }}{{ $b->published_at ? '' : ' · not published' }}</span></span>
                            <span class="tabular-nums font-semibold">{{ LedgerFormat::money($b->control_balance) }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-neutral-400">No imports.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <x-card title="A family's statement" subtitle="Open a student's ledger account; its page links to the family copy of the statement." id="statement">
            <form method="GET" action="{{ route('treasurer.reports.find-statement') }}" class="flex flex-col sm:flex-row gap-3">
                <label for="find-student" class="sr-only">Student ID or name</label>
                <input id="find-student" type="text" name="student" required placeholder="Student ID or name" class="flex-1 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Find</button>
            </form>
        </x-card>
    @endif
</x-app-layout>
