{{--
    Sun ledger statements, one card per financial year, newest first: what a family sees (T4: every
    line) and, read-only, leadership. $statements: SunFamilyStatement rows. $showHidden: list the
    lines the Treasurer hid by hand, marked (leadership with the policy switch on); otherwise they
    appear only as one "Adjustments" amount. Never pass $showHidden for a family.
--}}
@use('App\Support\LedgerFormat')
@php $showHidden ??= false; @endphp
@forelse ($statements as $statement)
    @php
        $c = $statement->content;
        $lines = collect($c['lines'])->map(fn ($l) => $l + ['hidden' => false]);
        if ($showHidden) {
            $lines = $lines->concat(collect($c['hidden'])->map(fn ($l) => $l + ['hidden' => true]))->sortBy([['month', 'asc'], ['id', 'asc']]);
        }
        $months = $lines->groupBy('month');
        $status = $statement->status();
        $tone = ['Owing' => 'text-danger', 'Settled' => 'text-success', 'In credit' => 'text-brand'][$status];
    @endphp
    <section class="bg-white rounded-2xl border border-neutral-200 overflow-hidden" data-statement="{{ $c['year_label'] }}">
        <header class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-4 border-b border-neutral-200">
            <div>
                <h2 class="text-lg font-bold text-ink">{{ $c['year_label'] }}</h2>
                <p class="text-xs text-neutral-500">School financial year, April to March</p>
            </div>
            <div class="text-right">
                <p class="text-xl font-bold tabular-nums {{ $tone }}">{{ LedgerFormat::money($c['closing']) }}</p>
                <p class="text-xs font-semibold {{ $tone }}" data-status>{{ $status }}</p>
            </div>
        </header>

        <div class="px-5 py-3 text-sm">
            <div class="flex justify-between gap-4 py-2 border-b border-neutral-100">
                <span class="text-neutral-600">Balance at the start of the year</span>
                <span class="tabular-nums font-semibold">{{ LedgerFormat::money($c['opening']) }}</span>
            </div>

            @foreach ($months as $month => $rows)
                <h3 class="mt-3 mb-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ \Illuminate\Support\Carbon::parse($month)->format('F Y') }}</h3>
                <ul class="divide-y divide-neutral-100">
                    @foreach ($rows as $line)
                        @php $credit = (float) $line['amount'] < 0; @endphp
                        <li class="flex justify-between gap-4 py-1.5 {{ $line['hidden'] ? 'bg-tint-yellow/40 -mx-2 px-2 rounded' : '' }}">
                            <span class="min-w-0">
                                {{ $line['memo'] !== '' ? $line['memo'] : ucfirst(str_replace('_', ' ', $line['category'])) }}
                                @if ($line['hidden'])<span class="ml-1 text-[11px] font-semibold text-tint-yellow-ink">hidden from families</span>@endif
                            </span>
                            <span class="tabular-nums whitespace-nowrap {{ $credit ? 'text-success' : '' }}">
                                {{ $credit ? '−'.number_format(abs((float) $line['amount']), 2) : number_format((float) $line['amount'], 2) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endforeach

            @if (! $showHidden && (float) $c['adjustments'] != 0)
                <div class="flex justify-between gap-4 py-2 mt-2 border-t border-neutral-100" data-adjustments>
                    <span class="text-neutral-600">Adjustments</span>
                    <span class="tabular-nums">{{ LedgerFormat::money($c['adjustments']) }}</span>
                </div>
            @endif

            <dl class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1 border-t border-neutral-200 pt-3">
                <div><dt class="text-xs text-neutral-500">Charged this year</dt><dd class="tabular-nums font-semibold">{{ number_format((float) $c['charges'], 2) }}</dd></div>
                <div><dt class="text-xs text-neutral-500">Paid and credited</dt><dd class="tabular-nums font-semibold text-success">{{ number_format((float) $c['credits'], 2) }}</dd></div>
                <div class="col-span-2 sm:col-span-1"><dt class="text-xs text-neutral-500">Balance at the end</dt><dd class="tabular-nums font-bold {{ $tone }}">{{ LedgerFormat::money($c['closing']) }} · {{ $status }}</dd></div>
            </dl>
        </div>
    </section>
@empty
    <x-card>
        <p class="text-sm text-neutral-500" data-no-statements>No fee records have been published yet. The finance office publishes them after each update from the school's accounts.</p>
    </x-card>
@endforelse
