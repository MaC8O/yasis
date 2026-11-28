@use('App\Support\LedgerFormat')
<x-app-layout :title="'Account '.$account->code" :subtitle="$account->name_as_printed.($fy ? ' · '.LedgerFormat::year($fy) : '')" badge="Treasurer" role="treasurer">
    <a href="{{ route('treasurer.ledger.accounts', ['year' => $fy]) }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-brand hover:underline">← Ledger Accounts</a>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <x-card title="Student">
            @if ($account->is_control)
                <p class="text-sm">Student Control: carries charges and receipts that aren't on a student's own account. Never linked to a student and never shown to families.</p>
            @else
                @if ($review)
                    <div class="mb-3 rounded-xl border border-warning-line bg-warning-soft px-3 py-2 text-sm text-warning" data-held>
                        Held for {{ LedgerFormat::year($fy) }}: printed as "{{ $review->printed_name }}", not "{{ $review->previous_name }}". Its lines this year match nobody until you resolve it on
                        <a href="{{ route('treasurer.ledger.review', ['year' => $fy]) }}" class="font-semibold underline">Ledger Review</a>.
                    </div>
                @endif
                @if ($owner)
                    <p class="text-sm" data-linked>{{ LedgerFormat::year($fy) }}: <span class="font-semibold">{{ $owner->name }}</span> ({{ $owner->student_id_number }}).
                        <a href="{{ route('treasurer.reports.statement', $owner) }}" target="_blank" class="ml-1 text-xs font-semibold text-brand hover:underline">Family copy of the statement (PDF)</a></p>
                @elseif (! $review)
                    <p class="text-sm text-warning">{{ $fy ? 'No student for '.LedgerFormat::year($fy).'.' : 'Not matched yet.' }}</p>
                @endif

                @if ($links->isNotEmpty())
                    <ul class="mt-3 space-y-1 text-xs text-neutral-600" data-links>
                        @foreach ($links as $link)
                            <li class="{{ $link->voided_at ? 'line-through text-neutral-400' : '' }}">
                                {{ $link->student->name }} ({{ $link->student->student_id_number }}): {{ LedgerFormat::year($link->from_fiscal_year) }}
                                {{ $link->to_fiscal_year ? 'to '.LedgerFormat::year($link->to_fiscal_year) : 'onwards' }}{{ $link->voided_at ? ' (voided: a mistake)' : '' }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($openLink)
                    <form method="POST" action="{{ route('treasurer.ledger.unlink', $account) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-danger hover:underline min-h-[36px]">Unlink (only to fix a mistake)</button>
                    </form>
                @elseif ($account->not_isms_student)
                    <p class="text-sm">Marked as not an ISMS student: its lines are seen by the Treasurer only.</p>
                    <form method="POST" action="{{ route('treasurer.ledger.not-isms', $account) }}" class="mt-2">@csrf<input type="hidden" name="not_isms" value="0">
                        <button type="submit" class="text-xs font-semibold text-brand hover:underline min-h-[36px]">Put it back in the unmatched list</button></form>
                @else
                    @foreach ($suggestions as $s)
                        <form method="POST" action="{{ route('treasurer.ledger.link', $account) }}" class="mt-2">@csrf
                            <input type="hidden" name="student_id_number" value="{{ $s['student']->student_id_number }}">
                            <button type="submit" class="rounded-lg border border-brand px-3 py-1.5 text-xs font-semibold text-brand min-h-[36px]">Link to {{ $s['student']->name }} ({{ $s['student']->student_id_number }}, {{ $s['score'] }}% alike)</button>
                        </form>
                    @endforeach
                    <form method="POST" action="{{ route('treasurer.ledger.link', $account) }}" class="mt-3 flex gap-2">@csrf
                        <label class="sr-only" for="acc-link">Student ID</label>
                        <input id="acc-link" type="text" name="student_id_number" required placeholder="Student ID" class="flex-1 min-w-0 rounded-lg border border-neutral-200 px-3 py-2 text-sm min-h-[40px]">
                        <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-xs min-h-[40px]">Link</button>
                    </form>
                @endif
            @endif
        </x-card>

        <x-card title="Balance">
            @if ($balance)
                <form method="GET" class="mb-3">
                    <label for="acc-year" class="sr-only">Financial year</label>
                    <select id="acc-year" name="year" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm min-h-[40px]">
                        @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $fy)>{{ LedgerFormat::year($y) }}</option>@endforeach
                    </select>
                </form>
                <dl class="grid grid-cols-3 gap-3 text-sm">
                    <div><dt class="text-neutral-500">Opening</dt><dd class="font-semibold tabular-nums">{{ LedgerFormat::money($balance->opening_balance) }}</dd></div>
                    <div><dt class="text-neutral-500">Closing</dt><dd class="font-semibold tabular-nums">{{ LedgerFormat::money($balance->closing_balance) }}</dd></div>
                    <div><dt class="text-neutral-500">Status</dt><dd class="font-semibold">{{ $account->is_control ? '—' : $balance->status() }}</dd></div>
                </dl>
            @else
                <p class="text-sm text-neutral-400">No balance for this year.</p>
            @endif
        </x-card>
    </div>

    <x-card :title="'Ledger lines ('.$lines->count().')'" subtitle="Every line, with its category and whether families see it. Your changes are kept when the ledger is imported again.">
        @if ($lines->isEmpty())
            <p class="text-sm text-neutral-400">No lines this year.</p>
        @else
            <div class="divide-y divide-neutral-100" data-lines>
                @foreach ($lines as $line)
                    <div class="py-3 grid gap-2 sm:grid-cols-[7rem_1fr_9rem] sm:items-start text-sm {{ $line->missing_from_latest ? 'opacity-70' : '' }}" data-line="{{ $line->id }}">
                        <div>
                            <span class="font-semibold">{{ $line->period_month->format('M Y') }}</span>
                            <span class="block text-xs {{ $line->date_suspect ? 'text-warning font-semibold' : 'text-neutral-500' }}">typed {{ $line->txn_date?->format('d/m/Y') ?? '—' }}{{ $line->date_suspect ? ' (suspect)' : '' }}</span>
                            <span class="block text-xs text-neutral-500">{{ $line->journal_type }} {{ $line->journal_no }}/{{ $line->journal_line }}{{ $line->allocated ? '' : ' · not allocated' }}</span>
                        </div>
                        <div>
                            <p>{{ $line->memo }}</p>
                            @if ($line->missing_from_latest)<p class="text-xs text-warning">Not in the latest file for this year.</p>@endif
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('treasurer.ledger.line', $line) }}" class="flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <label class="sr-only" for="cat-{{ $line->id }}">Category</label>
                                    <select id="cat-{{ $line->id }}" name="category" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-white px-2 py-1 text-xs min-h-[34px]">
                                        @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($line->category === $key)>{{ $label }}</option>@endforeach
                                    </select>
                                    @if ($line->category_overridden)<span class="text-[11px] text-neutral-500">set by hand</span>@endif
                                </form>
                                <form method="POST" action="{{ route('treasurer.ledger.line', $line) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="restricted" value="{{ $line->is_restricted ? 0 : 1 }}">
                                    <button type="submit" class="rounded-full px-2.5 py-1 text-[11px] font-semibold min-h-[30px] {{ $line->is_restricted ? 'bg-tint-yellow text-tint-yellow-ink' : 'bg-neutral-100 text-neutral-600' }}">
                                        {{ $line->is_restricted ? 'Hidden from families' : 'Shown to families' }}{{ $line->restricted_overridden ? ' (by hand)' : '' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="sm:text-right tabular-nums">
                            <span class="font-semibold">{{ LedgerFormat::money($line->amount) }}</span>
                            <span class="block text-xs text-neutral-500">balance {{ LedgerFormat::money($line->running_balance) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</x-app-layout>
