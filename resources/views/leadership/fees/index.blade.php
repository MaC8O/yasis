@use('App\Support\LedgerFormat')
<x-app-layout title="Fee Records" :subtitle="'Each student\'s balance'.($yearLabel ? ' for '.$yearLabel : '').', from the statements the Treasurer has published. The same figures families see. Read-only.'" badge="Read-only" :role="$role">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Owed to the school" color="yellow" :hint="$distribution['Owing'].' '.Str::plural('student', $distribution['Owing'])">{{ number_format($totals['owing']) }}</x-stat-tile>
        <x-stat-tile label="Settled" color="green" hint="balance 0">{{ $distribution['Settled'] }}</x-stat-tile>
        <x-stat-tile label="In credit" color="blue" :hint="number_format($totals['credit']).' paid in advance or overpaid'">{{ $distribution['In credit'] }}</x-stat-tile>
        <x-stat-tile label="Financial year" color="pink">{{ $yearLabel ?? '—' }}</x-stat-tile>
    </div>

    <x-card title="By department" subtitle="How many students owe, and how much.">
        @if ($byDepartment->isEmpty())
            <p class="text-sm text-neutral-400">Nothing published yet.</p>
        @else
            <x-chart.bar-list
                :items="$byDepartment->map(fn ($d) => ['label' => $d->name, 'value' => $d->owing_total, 'display' => number_format($d->owing_total).' · '.$d->owing.' owing'])"
                color="#B0392B" label-width="w-32" />
        @endif
    </x-card>

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <form method="GET" class="flex flex-wrap items-end gap-3 px-4 py-3 border-b border-neutral-200 bg-neutral-50">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Search</label>
                <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name, ID or class" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm min-h-[40px]">
            </div>
            <div>
                <label for="status" class="block text-[11px] font-semibold uppercase tracking-wide text-neutral-500 mb-1">Status</label>
                <select id="status" name="status" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm min-h-[40px]">
                    <option value="">All</option>
                    @foreach (['Owing', 'Settled', 'In credit'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm min-h-[40px] hover:bg-brand-dark">Apply</button>
        </form>
        <ul class="divide-y divide-neutral-100" data-fee-rows>
            @forelse ($rows as $row)
                <li>
                    <a href="{{ route($role.'.fees.show', $row->student) }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-brand-soft/40">
                        <span class="min-w-0">
                            <span class="font-medium text-ink">{{ $row->student->name }}</span>
                            <span class="block text-xs text-neutral-500 tabular-nums">{{ $row->student->student_id_number }}{{ $row->section ? ' · '.$row->section->name : '' }}</span>
                        </span>
                        <span class="text-right">
                            <span class="block font-bold tabular-nums">{{ LedgerFormat::money($row->closing) }}</span>
                            <span class="block text-xs font-semibold {{ ['Owing' => 'text-danger', 'Settled' => 'text-success', 'In credit' => 'text-brand'][$row->status] }}">{{ $row->status }}</span>
                        </span>
                    </a>
                </li>
            @empty
                <li class="py-8 px-4 text-center text-neutral-400">No published fee records match.</li>
            @endforelse
        </ul>
        <p class="px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs text-neutral-500">
            Read-only, published statements only. Amounts in Ks; "Cr" means in credit.
            {{ $showsHidden ? 'Lines the Treasurer hid from families are shown to you, marked.' : 'Lines the Treasurer hid from families show as one "Adjustments" amount.' }}
        </p>
    </div>
</x-app-layout>
