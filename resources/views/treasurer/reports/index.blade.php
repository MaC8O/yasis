@use('App\Support\Money')
@php
    $sections = [
        'who-owes' => 'Who still owes money?',
        'how-long' => 'How long has it been owed?',
        'collected' => 'How much have we collected?',
        'departments' => 'Which departments owe the most?',
        'statement' => "Print a student's statement",
    ];
    $download = 'inline-flex items-center gap-2 border border-brand text-brand font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-soft transition-colors';
    $q = 'text-lg font-bold text-ink';
@endphp
<x-app-layout title="Fee Reports" subtitle="School-wide fee totals. Each report answers one question — read it here or download it for Excel." badge="Treasurer" role="treasurer">
    {{-- The four numbers that matter --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-tile label="Total charged" color="blue" :hint="Money::CURRENCY">{{ Money::format($billedTotal) }}</x-stat-tile>
        <x-stat-tile label="Collected" color="green" :hint="$collectionRate !== null ? $collectionRate.'% of everything charged' : null">{{ Money::format($paidTotal) }}</x-stat-tile>
        <x-stat-tile label="Still owed" color="yellow" :hint="$owingCount.' '.Str::plural('student', $owingCount).' owing'" :href="route('treasurer.records.index', ['view' => 'owing', 'sort' => 'owed'])">{{ Money::format($owedTotal) }}</x-stat-tile>
        <x-stat-tile label="Unpaid over 90 days" color="pink" hint="Most urgent to follow up" :href="route('treasurer.records.index', ['view' => 'overdue', 'sort' => 'overdue'])">{{ Money::format($aging['over_90']) }}</x-stat-tile>
    </div>

    {{-- Jump to a report --}}
    <nav aria-label="Reports on this page" class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-semibold text-neutral-500 mr-1">Jump to:</span>
        @foreach ($sections as $id => $label)
            <a href="#{{ $id }}" class="rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-sm font-medium text-ink hover:border-brand hover:text-brand">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- 1. Who owes --}}
    <section id="who-owes" class="bg-white rounded-2xl border border-neutral-200 overflow-hidden scroll-mt-6">
        <div class="flex flex-wrap items-start justify-between gap-4 px-6 py-5 border-b border-neutral-200">
            <div>
                <h2 class="{{ $q }}">1. Who still owes money?</h2>
                <p class="text-sm text-neutral-500 mt-1">The {{ $topOwing->count() < $owingCount ? '10 largest of '.$owingCount : $owingCount }} unpaid {{ Str::plural('account', $owingCount) }}. The download has every one, with the guardian's name and phone for follow-up calls.</p>
            </div>
            <a href="{{ route('treasurer.reports.outstanding') }}" class="{{ $download }}"><x-icon name="upload" class="w-4 h-4 rotate-180" /> Download full list (Excel/CSV)</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200 bg-neutral-50">
                    <th class="py-2 pl-6 pr-2 text-left font-semibold">Student</th>
                    <th class="py-2 px-2 text-left font-semibold">Class</th>
                    <th class="py-2 px-2 text-right font-semibold">Still owed</th>
                    <th class="py-2 pl-2 pr-6 text-left font-semibold">Unpaid for</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($topOwing as $row)
                    <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50">
                        <td class="py-2 pl-6 pr-2"><a href="{{ route('treasurer.records.show', $row->student) }}" class="font-medium text-ink hover:underline">{{ $row->student->name }}</a> <span class="text-xs text-neutral-400 tabular-nums">{{ $row->student->student_id_number }}</span></td>
                        <td class="py-2 px-2 text-neutral-600">{{ $row->section?->name ?? '—' }}</td>
                        <td class="py-2 px-2 text-right tabular-nums font-bold">{{ Money::format($row->balance) }}</td>
                        <td class="py-2 pl-2 pr-6 text-sm {{ ($row->overdue_days ?? 0) > 90 ? 'text-danger font-semibold' : (($row->overdue_days ?? 0) > 0 ? 'text-warning font-semibold' : 'text-neutral-500') }}">
                            {{ $row->overdue_days ? $row->overdue_days.' days' : 'Under 30 days' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 px-6 text-center text-success font-semibold">Nobody owes money right now.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($owingCount > $topOwing->count())
            <p class="px-6 py-3 border-t border-neutral-200 bg-neutral-50 text-sm">
                <a href="{{ route('treasurer.records.index', ['view' => 'owing', 'sort' => 'owed']) }}" class="font-semibold text-brand hover:underline">See all {{ $owingCount }} in Student Accounts →</a>
            </p>
        @endif
    </section>

    {{-- 2. How long --}}
    <section id="how-long" class="bg-white rounded-2xl border border-neutral-200 overflow-hidden scroll-mt-6">
        <div class="flex flex-wrap items-start justify-between gap-4 px-6 py-5 border-b border-neutral-200">
            <div>
                <h2 class="{{ $q }}">2. How long has it been owed?</h2>
                <p class="text-sm text-neutral-500 mt-1">All unpaid money, grouped by how long ago it was charged. The older it is, the harder it is to collect.</p>
            </div>
            <a href="{{ route('treasurer.reports.aging') }}" class="{{ $download }}"><x-icon name="upload" class="w-4 h-4 rotate-180" /> Download by student (Excel/CSV)</a>
        </div>
        <div class="px-6 py-5">
            <x-finance.aging-strip :aging="$aging" :counts="$agingCounts" />
        </div>
    </section>

    {{-- 3. Collected --}}
    <section id="collected" class="bg-white rounded-2xl border border-neutral-200 overflow-hidden scroll-mt-6">
        <div class="px-6 py-5 border-b border-neutral-200">
            <h2 class="{{ $q }}">3. How much have we collected?</h2>
            <p class="text-sm text-neutral-500 mt-1">For each import period: what was charged, how much of it has been paid, and what is still outstanding.</p>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200 bg-neutral-50">
                    <th class="py-2 pl-6 pr-2 text-left font-semibold">Period</th>
                    <th class="py-2 px-2 text-right font-semibold">Charged</th>
                    <th class="py-2 px-2 text-right font-semibold">Collected</th>
                    <th class="py-2 px-2 text-right font-semibold">Still owed</th>
                    <th class="py-2 pl-4 pr-6 text-left font-semibold w-1/3">Collected so far</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byPeriod as $row)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5 pl-6 pr-2 font-medium text-ink">{{ $row->period }}</td>
                        <td class="py-2.5 px-2 text-right tabular-nums">{{ Money::format($row->billed) }}</td>
                        <td class="py-2.5 px-2 text-right tabular-nums text-success">{{ Money::format($row->collected) }}</td>
                        <td class="py-2.5 px-2 text-right tabular-nums font-semibold">{{ Money::format($row->owed, true) }}</td>
                        <td class="py-2.5 pl-4 pr-6">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 rounded-full bg-neutral-100 overflow-hidden" aria-hidden="true">
                                    <div class="h-full bg-success" @style(['width: '.min(100, $row->rate).'%'])></div>
                                </div>
                                <span class="w-12 text-right tabular-nums font-semibold">{{ $row->rate }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 px-6 text-center text-neutral-400">No imported periods yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- 4. Departments --}}
    <section id="departments" class="bg-white rounded-2xl border border-neutral-200 overflow-hidden scroll-mt-6">
        <div class="px-6 py-5 border-b border-neutral-200">
            <h2 class="{{ $q }}">4. Which departments owe the most?</h2>
            <p class="text-sm text-neutral-500 mt-1">Unpaid money by school department, so follow-up can be shared out.</p>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200 bg-neutral-50">
                    <th class="py-2 pl-6 pr-2 text-left font-semibold">Department</th>
                    <th class="py-2 px-2 text-right font-semibold">Students owing</th>
                    <th class="py-2 px-2 text-right font-semibold">Still owed</th>
                    <th class="py-2 pl-4 pr-6 text-left font-semibold w-1/3">Share of all unpaid money</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byDepartment as $row)
                    @php $share = $owedTotal > 0 ? round($row->outstanding / $owedTotal * 100) : 0; @endphp
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5 pl-6 pr-2 font-medium text-ink">{{ $row->department }}</td>
                        <td class="py-2.5 px-2 text-right tabular-nums">{{ $row->students }}</td>
                        <td class="py-2.5 px-2 text-right tabular-nums font-semibold">{{ Money::format($row->outstanding) }}</td>
                        <td class="py-2.5 pl-4 pr-6">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 rounded-full bg-neutral-100 overflow-hidden" aria-hidden="true">
                                    <div class="h-full bg-danger" @style(['width: '.$share.'%'])></div>
                                </div>
                                <span class="w-12 text-right tabular-nums font-semibold">{{ $share }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 px-6 text-center text-success font-semibold">No unpaid money in any department.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- 5. One student's statement --}}
    <section id="statement" class="bg-white rounded-2xl border border-neutral-200 px-6 py-5 scroll-mt-6">
        <h2 class="{{ $q }}">5. Print a student's statement</h2>
        <p class="text-sm text-neutral-500 mt-1">Pick a student to open their statement. From there you can print an office copy, or a copy that is safe to give the family.</p>
        @if (session('warning'))
            <p role="alert" class="mt-3 flex items-center gap-2 text-sm text-warning font-semibold"><x-icon name="alert" class="w-4 h-4" />{{ session('warning') }}</p>
        @endif
        <form method="GET" action="{{ route('treasurer.reports.find-statement') }}" class="mt-4 flex flex-wrap gap-2" x-data="{ v: '' }">
            <label for="statement-student" class="sr-only">Student name or ID</label>
            <input id="statement-student" name="student" x-model="v" list="statement-students" autocomplete="off" placeholder="Type a student's name or ID…"
                   class="w-80 max-w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
            <datalist id="statement-students">
                @foreach ($statementStudents as $s)
                    <option value="{{ $s->student_id_number }}">{{ $s->name }}</option>
                @endforeach
            </datalist>
            <button type="submit" :disabled="!v.trim()" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">Open statement</button>
        </form>
    </section>

    <p class="text-xs text-neutral-400">
        These reports include every imported line. Principal, VP and Registrar see the same figures under their own
        <a href="{{ route('treasurer.info.visibility-rules') }}" class="font-semibold text-brand hover:underline">visibility rules</a>. Each download is recorded in the audit log.
    </p>
</x-app-layout>
