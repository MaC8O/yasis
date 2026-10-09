@use('App\Support\Money')
<x-app-layout title="Student Accounts" subtitle="Look up any student to see what they were charged, what they've paid and what they still owe." badge="Treasurer" role="treasurer">
    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        {{-- What to show: one click, no Apply button --}}
        <nav class="flex flex-wrap gap-1 px-3 pt-3 border-b border-neutral-200" aria-label="Which accounts">
            @foreach ($views as $key => $label)
                <a href="{{ route('treasurer.records.index', array_filter(['view' => $key === 'all' ? null : $key, 'search' => $filters['search'] ?: null, 'sort' => $filters['sort'] !== 'name' ? $filters['sort'] : null])) }}"
                   @if ($view === $key) aria-current="page" @endif
                   class="flex items-center gap-2 rounded-t-lg px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors
                          {{ $view === $key ? 'border-brand text-brand bg-brand-soft/60' : 'border-transparent text-neutral-500 hover:text-ink hover:bg-neutral-50' }}">
                    {{ $label }}
                    <span class="rounded-full px-2 py-0.5 text-xs tabular-nums {{ $view === $key ? 'bg-brand text-white' : 'bg-neutral-100 text-neutral-600' }}">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" class="flex flex-wrap items-center gap-3 px-4 py-3 bg-neutral-50 border-b border-neutral-200">
            @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
            <div class="flex-1 min-w-[220px]">
                <label for="search" class="sr-only">Search students</label>
                <input id="search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search by name, student ID or class…"
                       class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
            </div>
            <label for="sort" class="text-sm text-neutral-500">Sort by</label>
            <select id="sort" name="sort" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                <option value="name" @selected($filters['sort'] === 'name')>Name (A–Z)</option>
                <option value="owed" @selected($filters['sort'] === 'owed')>Owes the most</option>
                <option value="overdue" @selected($filters['sort'] === 'overdue')>Unpaid the longest</option>
            </select>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">Search</button>
            @if ($filters['search'])
                <a href="{{ route('treasurer.records.index', array_filter(['view' => $view === 'all' ? null : $view])) }}" class="text-sm font-semibold text-neutral-500 hover:underline">Clear search</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200">
                        <th class="py-2.5 pl-4 pr-2 text-left font-semibold">Student</th>
                        <th class="py-2.5 px-2 text-left font-semibold">Class</th>
                        <th class="py-2.5 px-2 text-right font-semibold">Charged</th>
                        <th class="py-2.5 px-2 text-right font-semibold">Paid</th>
                        <th class="py-2.5 px-2 text-right font-semibold">Still owed</th>
                        <th class="py-2.5 px-2 text-left font-semibold">Status</th>
                        <th class="py-2.5 pl-2 pr-4"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        @php $url = route('treasurer.records.show', $summary->student); @endphp
                        <tr class="group border-b border-neutral-100 hover:bg-brand-soft/40 cursor-pointer" onclick="window.location='{{ $url }}'">
                            <td class="py-2.5 pl-4 pr-2">
                                <a href="{{ $url }}" class="font-semibold text-ink group-hover:underline">{{ $summary->student->name }}</a>
                                <p class="text-xs text-neutral-400 tabular-nums">{{ $summary->student->student_id_number }}</p>
                            </td>
                            <td class="py-2.5 px-2 text-neutral-600 whitespace-nowrap">{{ $summary->section?->name ?? $summary->student->department?->name ?? '—' }}</td>
                            <td class="py-2.5 px-2 text-right tabular-nums text-neutral-600">{{ Money::format($summary->total_billed) }}</td>
                            <td class="py-2.5 px-2 text-right tabular-nums text-success">{{ Money::format($summary->paid, true) }}</td>
                            <td class="py-2.5 px-2 text-right tabular-nums font-bold {{ $summary->balance > 0 ? 'text-ink' : 'text-neutral-300' }}">{{ Money::format($summary->balance, true) }}</td>
                            <td class="py-2.5 px-2 whitespace-nowrap">
                                @if ($summary->balance <= 0)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-success"><x-icon name="check" class="w-3.5 h-3.5" /> Fully paid</span>
                                @elseif ($summary->overdue_days)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold {{ $summary->overdue_days > 90 ? 'text-danger' : 'text-warning' }}">
                                        <x-icon name="clock" class="w-3.5 h-3.5" /> Unpaid {{ $summary->overdue_days }} days
                                    </span>
                                @else
                                    <span class="text-xs font-semibold text-neutral-500">Owing · not yet overdue</span>
                                @endif
                            </td>
                            <td class="py-2.5 pl-2 pr-4 text-right whitespace-nowrap">
                                <span class="text-xs font-semibold text-brand opacity-70 group-hover:opacity-100">Statement →</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 px-4 text-center text-neutral-500">
                                @if ($filters['search'])
                                    No student matches “{{ $filters['search'] }}” in this list.
                                @elseif ($view === 'paid')
                                    No fully paid accounts yet.
                                @elseif ($view !== 'all')
                                    Nobody in this list — good news.
                                @else
                                    No fee records yet. Import the finance office's export from <a href="{{ route('treasurer.import.index') }}" class="font-semibold text-brand hover:underline">Import Records</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($summaries->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-neutral-300 bg-neutral-50 font-bold tabular-nums">
                            <td class="py-2.5 pl-4 pr-2" colspan="2">{{ $summaries->count() }} {{ Str::plural('student', $summaries->count()) }} shown</td>
                            <td class="py-2.5 px-2 text-right">{{ Money::format($totals['charged']) }}</td>
                            <td class="py-2.5 px-2 text-right text-success">{{ Money::format($totals['paid']) }}</td>
                            <td class="py-2.5 px-2 text-right">{{ Money::format($totals['owed']) }}</td>
                            <td class="py-2.5 pl-2 pr-4" colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs text-neutral-500">
            Amounts in {{ Money::CURRENCY }}. “Overdue” means a charge has been unpaid for more than 30 days.
            Click a student for their full statement, which you can print for the office or for the family.
            For school-wide totals and downloads, see <a href="{{ route('treasurer.reports.index') }}" class="font-semibold text-brand hover:underline">Fee Reports</a>.
        </p>
    </div>
</x-app-layout>
