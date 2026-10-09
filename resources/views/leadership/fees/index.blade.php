@use('App\Support\Money')
<x-app-layout title="Fee Records" subtitle="What students have been charged, what they've paid and what they still owe — from the Treasurer's published records. Read-only." badge="Read-only" :role="$role">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Still owed ({{ Money::CURRENCY }})" color="yellow">{{ Money::format($outstandingTotal) }}</x-stat-tile>
        <x-stat-tile label="Collected ({{ Money::CURRENCY }})" color="green">{{ Money::format($paidTotal) }}</x-stat-tile>
        <x-stat-tile label="Collected so far" color="blue" hint="of everything charged">{{ $collectionRate !== null ? $collectionRate.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="Unpaid over 90 days ({{ Money::CURRENCY }})" color="pink">{{ Money::format($aging['over_90']) }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="How long the money has been owed">
            <x-finance.aging-strip :aging="$aging" compact />
        </x-card>
        <x-card title="Still owed by department">
            <x-chart.bar-list
                :items="collect($byDepartment)->map(fn ($row) => ['label' => $row->department, 'value' => $row->outstanding, 'display' => Money::format($row->outstanding)])"
                color="#B0392B" label-width="w-32" />
        </x-card>
    </div>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Search</label>
                <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name, ID or class" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
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
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">Apply</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b-2 border-neutral-300">
                        <th class="py-2 pl-4 pr-2 text-left font-semibold">Student</th>
                        <th class="py-2 px-2 text-left font-semibold">Class</th>
                        <th class="py-2 px-2 text-right font-semibold">Charged</th>
                        <th class="py-2 px-2 text-right font-semibold">Paid</th>
                        <th class="py-2 px-2 text-right font-semibold">Still owed</th>
                        <th class="py-2 px-2 text-left font-semibold">Status</th>
                        <th class="py-2 pl-2 pr-4"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        @php $url = route($role.'.fees.show', $summary->student); @endphp
                        <tr class="group border-b border-neutral-100 hover:bg-brand-soft/40 cursor-pointer" onclick="window.location='{{ $url }}'">
                            <td class="py-2 pl-4 pr-2">
                                <a href="{{ $url }}" class="font-medium text-ink group-hover:underline">{{ $summary->student->name }}</a>
                                <p class="text-xs text-neutral-400 tabular-nums">
                                    {{ $summary->student->student_id_number }}
                                    @if ($showsRestricted && $summary->is_restricted)<span class="ml-1 text-tint-purple-ink font-semibold">· SDA</span>@endif
                                </p>
                            </td>
                            <td class="py-2 px-2 text-neutral-500">{{ $summary->section?->name ?? $summary->student->department?->name }}</td>
                            <td class="py-2 px-2 text-right tabular-nums">{{ Money::format($summary->total_billed) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums text-success">{{ Money::format($summary->paid, true) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums font-bold">{{ Money::format($summary->balance, true) }}</td>
                            <td class="py-2 px-2"><x-finance.status-pill :status="$summary->status" /></td>
                            <td class="py-2 pl-2 pr-4 text-right whitespace-nowrap"><span class="text-xs font-semibold text-brand opacity-70 group-hover:opacity-100">Statement →</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 px-4 text-center text-neutral-400">No published fee records match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs text-neutral-500">
            Read-only. Shows published, matched records only{{ $showsRestricted ? ', including restricted (SDA) rows (flagged)' : '; restricted (SDA) rows are hidden' }}.
            Amounts in {{ Money::CURRENCY }}. Click a student to see their full statement.
        </p>
    </div>
</x-app-layout>
