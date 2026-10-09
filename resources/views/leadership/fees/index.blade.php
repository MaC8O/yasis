@use('App\Support\Money')
<x-app-layout title="Fee Records" subtitle="Read-only view of published fee records imported by the Treasurer." badge="Read-only" :role="$role">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Receivables ({{ Money::CURRENCY }})" color="yellow">{{ Money::format($outstandingTotal) }}</x-stat-tile>
        <x-stat-tile label="Collected ({{ Money::CURRENCY }})" color="green">{{ Money::format($paidTotal) }}</x-stat-tile>
        <x-stat-tile label="Collection rate" color="blue">{{ $collectionRate !== null ? $collectionRate.'%' : '—' }}</x-stat-tile>
        <x-stat-tile label="Over 90 days ({{ Money::CURRENCY }})" color="pink">{{ Money::format($aging['over_90']) }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Aged receivables">
            <x-finance.aging-strip :aging="$aging" compact />
        </x-card>
        <x-card title="Receivables by department">
            <x-chart.bar-list
                :items="collect($byDepartment)->map(fn ($row) => ['label' => $row->department, 'value' => $row->outstanding, 'display' => Money::format($row->outstanding)])"
                color="#B0392B" label-width="w-32" />
        </x-card>
    </div>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Search</label>
                <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name or ID" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
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
                        <th class="py-2 px-2 text-right font-semibold">Billed</th>
                        <th class="py-2 px-2 text-right font-semibold">Paid</th>
                        <th class="py-2 px-2 text-right font-semibold">Balance</th>
                        <th class="py-2 pl-2 pr-4 text-left font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        <tr class="border-b border-neutral-100 hover:bg-neutral-50">
                            <td class="py-2 pl-4 pr-2">
                                <a href="{{ route($role.'.fees.show', $summary->student) }}" class="font-medium text-ink hover:underline">{{ $summary->student->name }}</a>
                                <p class="text-xs text-neutral-400 tabular-nums">
                                    {{ $summary->student->student_id_number }}
                                    @if ($showsRestricted && $summary->is_restricted)<span class="ml-1 text-tint-purple-ink font-semibold">· SDA</span>@endif
                                </p>
                            </td>
                            <td class="py-2 px-2 text-neutral-500">{{ $summary->section?->name ?? $summary->student->department?->name }}</td>
                            <td class="py-2 px-2 text-right tabular-nums">{{ Money::format($summary->total_billed) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums text-success">{{ Money::format($summary->paid, true) }}</td>
                            <td class="py-2 px-2 text-right tabular-nums font-bold">{{ Money::format($summary->balance, true) }}</td>
                            <td class="py-2 pl-2 pr-4"><x-finance.status-pill :status="$summary->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 px-4 text-center text-neutral-400">No published fee records match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs text-neutral-500">
            Read-only. Shows published, matched records only{{ $showsRestricted ? ', including restricted (SDA) rows (flagged)' : '; restricted (SDA) rows are hidden' }}. Amounts in {{ Money::CURRENCY }}.
        </p>
    </div>
</x-app-layout>
