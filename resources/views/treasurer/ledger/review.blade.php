@use('App\Support\LedgerFormat')
<x-app-layout title="Ledger Review" subtitle="Match ledger accounts to students and check what the import flagged. Nothing here changes Sun." badge="Treasurer" role="treasurer">
    @if ($years->isEmpty())
        <x-card><p class="text-sm text-neutral-400">Nothing imported yet. Start on <a href="{{ route('treasurer.ledger.index') }}" class="text-brand font-semibold hover:underline">Ledger Import</a>.</p></x-card>
    @else
        <x-card>
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="lr-year" class="block text-sm font-semibold mb-1">Financial year</label>
                    <select id="lr-year" name="year" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                        @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $fy)>{{ LedgerFormat::year($y) }}</option>@endforeach
                    </select>
                </div>
                @if ($control)
                    <p class="text-sm">Unallocated in Student Control: <span class="font-semibold tabular-nums">{{ LedgerFormat::money($control->closing_balance) }}</span></p>
                @endif
            </form>
        </x-card>

        {{-- Unmatched accounts --}}
        <x-card :title="'Accounts to match ('.$unmatched->total().')'" subtitle="Link each account code to its student. Names only suggest candidates; nothing is linked by name. Old accounts of students who left before the ISMS can be marked as not ISMS students.">
            @if ($unmatched->isEmpty())
                <p class="text-sm text-success" data-none-unmatched>Every account in this year is matched.</p>
            @else
                <div class="divide-y divide-neutral-100" data-unmatched>
                    @foreach ($unmatched as $account)
                        <div class="py-3 grid gap-3 lg:grid-cols-[1fr_1.3fr] lg:items-start" data-account="{{ $account->code }}">
                            <div class="text-sm">
                                <a href="{{ route('treasurer.ledger.account', [$account, 'year' => $fy]) }}" class="font-semibold text-brand hover:underline tabular-nums">{{ $account->code }}</a>
                                <span class="block">{{ $account->name_as_printed }}</span>
                                <span class="block text-xs text-neutral-500">Closing {{ LedgerFormat::money($unmatchedClosing[$account->id] ?? 0) }} · first seen {{ LedgerFormat::year($account->first_seen_year) }}</span>
                            </div>
                            <div class="space-y-2">
                                @if ($suggestions[$account->id])
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($suggestions[$account->id] as $s)
                                            <form method="POST" action="{{ route('treasurer.ledger.link', $account) }}">
                                                @csrf
                                                <input type="hidden" name="student_id_number" value="{{ $s['student']->student_id_number }}">
                                                <button type="submit" class="rounded-lg border border-brand px-3 py-1.5 text-xs font-semibold text-brand hover:bg-brand-soft min-h-[36px]">Link to {{ $s['student']->name }} ({{ $s['student']->student_id_number }}, {{ $s['score'] }}% alike)</button>
                                            </form>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <form method="POST" action="{{ route('treasurer.ledger.link', $account) }}" class="flex gap-2 flex-1">
                                        @csrf
                                        <label class="sr-only" for="link-{{ $account->id }}">Student ID for {{ $account->code }}</label>
                                        <input id="link-{{ $account->id }}" type="text" name="student_id_number" required placeholder="Student ID, e.g. YAS-2026-0001" class="flex-1 min-w-0 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm min-h-[40px]">
                                        <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-xs min-h-[40px]">Link</button>
                                    </form>
                                    <form method="POST" action="{{ route('treasurer.ledger.not-isms', $account) }}">
                                        @csrf
                                        <input type="hidden" name="not_isms" value="1">
                                        <button type="submit" class="w-full sm:w-auto border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-3 py-2 text-xs min-h-[40px]">Not an ISMS student</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3">{{ $unmatched->links() }}</div>
            @endif
        </x-card>

        {{-- Fee schedule check --}}
        <x-card title="Charges against the fee schedule" subtitle="Flagged for review, never refused. Totals for the year only: tuition may be the year's amount or a whole number of instalments.">
            @if (! $schedule['has_schedule'])
                <p class="text-sm text-neutral-600" data-no-schedule>No fee schedule for {{ $schedule['label'] }}, so this year's charges aren't checked against one.</p>
            @elseif ($schedule['rows'] === [])
                <p class="text-sm text-success">Every matched student's charges agree with the {{ $schedule['label'] }} fee schedule.</p>
            @else
                <ul class="divide-y divide-neutral-100 text-sm" data-schedule-differences>
                    @foreach ($schedule['rows'] as $row)
                        <li class="py-2">
                            <a href="{{ route('treasurer.ledger.account', [$row['account'], 'year' => $fy]) }}" class="font-semibold text-brand hover:underline">{{ $row['account']->student?->name }}</a>
                            <span class="text-neutral-500">· {{ $row['account']->code }}{{ $row['grade'] ? ' · '.$row['grade'] : '' }}{{ $row['reversals'] ? ' · '.$row['reversals'].' reversal '.Str::plural('line', $row['reversals']) : '' }}</span>
                            <ul class="mt-0.5 text-xs text-neutral-600">
                                @foreach ($row['issues'] as $issue)
                                    <li>{{ is_string($issue) ? $issue : $issue['fee'].': charged '.number_format($issue['charged']).' Ks, schedule '.number_format($issue['expected']).' Ks' }}</li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card :title="'Unclassified lines ('.$unclassifiedCount.')'" subtitle="No category rule matched the memo. Set a category on the account page, or add a keyword on Fee Categories.">
            @include('treasurer.ledger.partials.lines', ['lines' => $unclassified, 'total' => $unclassifiedCount, 'fy' => $fy, 'empty' => 'Every line has a category.'])
        </x-card>

        <x-card :title="'Suspect dates ('.$suspectCount.')'" subtitle="The typed date is more than a month from the accounting period. The period is what counts; the typed date is kept as entered.">
            @include('treasurer.ledger.partials.lines', ['lines' => $suspect, 'total' => $suspectCount, 'fy' => $fy, 'empty' => 'No suspect dates.'])
        </x-card>

        <x-card :title="'Lines missing from the latest file ('.$missing->count().')'" subtitle="In the ISMS but not in the latest listing for this year. Kept, not deleted: something was changed in Sun.">
            @include('treasurer.ledger.partials.lines', ['lines' => $missing, 'total' => $missing->count(), 'fy' => $fy, 'empty' => 'None.'])
        </x-card>

        <x-card :title="'Amounts changed by the latest import ('.$changed->count().')'">
            @include('treasurer.ledger.partials.lines', ['lines' => $changed, 'total' => $changed->count(), 'fy' => $fy, 'empty' => 'None.'])
        </x-card>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card :title="'Renamed accounts ('.$renamed->count().')'" :subtitle="'The name differs from '.LedgerFormat::year($fy - 1).'. The code is what matches.'">
                @forelse ($renamed as $r)
                    <p class="text-sm py-1"><span class="tabular-nums font-semibold">{{ $r['balance']->account->code }}</span>: {{ $r['before'] }} → {{ $r['balance']->account_name_as_printed }}</p>
                @empty
                    <p class="text-sm text-neutral-400">None.</p>
                @endforelse
            </x-card>
            <x-card :title="'Opening balance differs from last year ('.$openingDiffers->count().')'" :subtitle="'Opening '.LedgerFormat::year($fy).' against closing '.LedgerFormat::year($fy - 1).', for example year-end adjustments.'">
                @forelse ($openingDiffers as $r)
                    <p class="text-sm py-1"><a href="{{ route('treasurer.ledger.account', [$r['balance']->account, 'year' => $fy]) }}" class="tabular-nums font-semibold text-brand hover:underline">{{ $r['balance']->account->code }}</a>:
                        opening {{ LedgerFormat::money($r['balance']->opening_balance) }}, closed {{ LedgerFormat::money($r['previous_closing']) }}</p>
                @empty
                    <p class="text-sm text-neutral-400">None.</p>
                @endforelse
            </x-card>
        </div>
    @endif
</x-app-layout>
