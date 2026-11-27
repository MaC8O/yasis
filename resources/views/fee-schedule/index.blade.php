{{-- §10 fee schedule: the Treasurer edits and publishes it; Principal, VP and Registrar read it. --}}
@use('App\Support\Money')
@php
    $badge = ['treasurer' => 'Treasurer', 'principal' => 'Principal', 'vp_academic' => 'VP Academic', 'registrar' => 'Registrar'][$portal] ?? 'Staff';
    $flag = fn (?string $q) => $q ? '<span class="ml-1 rounded-full bg-tint-yellow text-tint-yellow-ink px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap">'.e($q).'</span>' : '';
@endphp
<x-app-layout title="Fee Schedule" :subtitle="$readOnly
        ? 'The published fees for each grade, set by the Treasurer. Reference only: payments are recorded by the finance office.'
        : 'The fees for each grade and school year. Reference only: the ISMS never records payments. Families see a schedule once it is published.'"
    :badge="$badge" :role="$portal">

    <x-card>
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="fs-year" class="block text-sm font-semibold mb-1">School year</label>
                <select id="fs-year" name="year" onchange="this.form.submit()" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                    @foreach ($years as $y)
                        <option value="{{ $y->id }}" @selected($year && $y->id === $year->id)>{{ $y->year_label }}{{ $y->is_active ? ' (current)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            @if ($schedule)
                <p class="text-sm {{ $schedule->isPublished() ? 'text-success' : 'text-warning' }}" data-publish-state>
                    @if ($schedule->isPublished())
                        Published {{ $schedule->published_at->format('j M Y') }}{{ $schedule->publishedBy ? ' by '.$schedule->publishedBy->name : '' }}. Families see it.
                    @else
                        Not published. Families don't see it yet.
                    @endif
                </p>
            @endif
        </form>
    </x-card>

    @if (! $schedule)
        <x-card title="No fee schedule for {{ $year?->year_label }}">
            @if ($readOnly)
                <p class="text-sm text-neutral-500">The Treasurer hasn't set this year's fees yet.</p>
            @elseif ($year)
                <form method="POST" action="{{ route('treasurer.fee-schedule.start', $year) }}">
                    @csrf
                    <p class="text-sm text-neutral-600 mb-3">Starting it copies the latest earlier year's amounts. It starts unpublished and without the earlier year's question flags; check every amount, then publish it.</p>
                    <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Start the schedule for {{ $year->year_label }}</button>
                </form>
            @endif
        </x-card>
    @else
        <x-card title="As on the school's fee sheet" subtitle="Grades with the same amount are grouped.">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-sm" data-summary>
                @foreach (\App\Models\FeeScheduleItem::TYPES as $type => $label)
                    <div>
                        <h3 class="font-semibold mb-1">{{ $label }}{{ $type === 'deposit' ? ' (new students only)' : '' }}</h3>
                        <ul class="space-y-1">
                            @foreach ($summary[$type] ?? [] as $run)
                                <li class="flex justify-between gap-3">
                                    <span class="text-neutral-600">{{ $run['levels'] }}</span>
                                    <span class="tabular-nums font-semibold text-right">{{ Money::format($run['amount']) }} Ks
                                        @if ($type === 'tuition' && $run['instalments'] > 1)
                                            <span class="block text-xs font-normal text-neutral-500">{{ $run['instalments'] }} instalments of {{ Money::format($service->instalments($run['amount'], $run['instalments'])[0]) }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-neutral-500">Tuition is paid in instalments starting when the child is first registered. The yearly fee and the deposit are one-time charges.</p>
        </x-card>

        <x-card :title="$readOnly ? 'Fees by grade' : 'Edit fees by grade'" subtitle="Whole kyats. Instalments apply to tuition; the last instalment takes any remainder.">
            @unless ($readOnly)
                <form method="POST" action="{{ route('treasurer.fee-schedule.update', $schedule) }}" data-fee-form>
                    @csrf @method('PUT')
            @endunless

            <div class="hidden sm:grid sm:grid-cols-[1.2fr_1fr_1fr_0.7fr_1fr_1fr] gap-3 border-b border-neutral-200 pb-2 text-xs font-semibold text-neutral-500">
                <span>Grade</span><span>Yearly fee</span><span>Tuition (year)</span><span>Instalments</span><span>Per instalment</span><span>Deposit (new)</span>
            </div>
            <div class="divide-y divide-neutral-100">
                @foreach ($levels as $level)
                    @php
                        $row = $items[$level->id] ?? collect();
                        $tuition = $row['tuition'] ?? null;
                    @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-[1.2fr_1fr_1fr_0.7fr_1fr_1fr] gap-x-3 gap-y-2 py-3 text-sm" data-level="{{ $level->code }}">
                        <div class="col-span-2 sm:col-span-1 font-semibold self-center">{{ $level->name }}</div>
                        @foreach (['yearly', 'tuition'] as $type)
                            @php $item = $row[$type] ?? null; @endphp
                            <div>
                                <span class="sm:sr-only block text-xs text-neutral-500">{{ \App\Models\FeeScheduleItem::TYPES[$type] }}{{ $type === 'tuition' ? ' (year)' : '' }}</span>
                                @if ($item)
                                    @if ($readOnly)
                                        <span class="tabular-nums">{{ Money::format($item->amount) }}</span>{!! $flag($item->pending_question) !!}
                                    @else
                                        <input type="number" min="0" step="1" required name="items[{{ $item->id }}][amount]" aria-label="{{ $level->name }} {{ \App\Models\FeeScheduleItem::TYPES[$type] }}"
                                               value="{{ old('items.'.$item->id.'.amount', (int) $item->amount) }}" class="w-full rounded-lg border border-neutral-200 bg-white px-2 py-2 text-sm tabular-nums min-h-[40px]">
                                        {!! $flag($item->pending_question) !!}
                                    @endif
                                @else — @endif
                            </div>
                        @endforeach
                        <div>
                            <span class="sm:sr-only block text-xs text-neutral-500">Instalments</span>
                            @if ($tuition)
                                @if ($readOnly)
                                    {{ $tuition->instalments }}
                                @else
                                    <input type="number" min="1" max="12" step="1" required name="items[{{ $tuition->id }}][instalments]" aria-label="{{ $level->name }} tuition instalments"
                                           value="{{ old('items.'.$tuition->id.'.instalments', $tuition->instalments) }}" class="w-full rounded-lg border border-neutral-200 bg-white px-2 py-2 text-sm min-h-[40px]">
                                @endif
                            @endif
                        </div>
                        <div class="self-center">
                            <span class="sm:sr-only block text-xs text-neutral-500">Per instalment</span>
                            @if ($tuition)
                                @php $parts = $service->instalments((string) $tuition->amount, (int) $tuition->instalments); @endphp
                                <span class="tabular-nums" data-per-instalment>{{ Money::format($parts[0]) }}</span>
                                @if (end($parts) !== $parts[0])<span class="block text-xs text-neutral-500">last {{ Money::format(end($parts)) }}</span>@endif
                            @endif
                        </div>
                        @php $deposit = $row['deposit'] ?? null; @endphp
                        <div>
                            <span class="sm:sr-only block text-xs text-neutral-500">Deposit (new students)</span>
                            @if ($deposit)
                                @if ($readOnly)
                                    <span class="tabular-nums">{{ Money::format($deposit->amount) }}</span>{!! $flag($deposit->pending_question) !!}
                                @else
                                    <input type="number" min="0" step="1" required name="items[{{ $deposit->id }}][amount]" aria-label="{{ $level->name }} deposit"
                                           value="{{ old('items.'.$deposit->id.'.amount', (int) $deposit->amount) }}" class="w-full rounded-lg border border-neutral-200 bg-white px-2 py-2 text-sm tabular-nums min-h-[40px]">
                                    {!! $flag($deposit->pending_question) !!}
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($pending)
                <div class="mt-4 rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning" data-pending>
                    <p class="font-semibold">Defaults waiting for the school's answer: {{ implode(', ', $pending) }}</p>
                    <p class="mt-0.5 text-xs">Q17: Grade 9 tuition is 2,600,000 Ks as printed. Q18: tuition in four equal instalments; the yearly fee and the deposit are separate one-time lines.</p>
                    @unless ($readOnly)
                        <div class="mt-2 flex flex-wrap gap-x-6">
                            @foreach ($pending as $q)
                                <label class="flex items-center gap-2 min-h-[44px]">
                                    <input type="checkbox" name="confirmed[]" value="{{ $q }}" class="rounded border-neutral-300 text-brand"> Confirmed by the school ({{ $q }})
                                </label>
                            @endforeach
                        </div>
                    @endunless
                </div>
            @endif

            @unless ($readOnly)
                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                        @if ($schedule->isPublished())
                            <div>
                                <label for="fs-reason" class="block text-sm font-semibold mb-1">Reason for the change <span class="font-normal text-neutral-500">(families can see this schedule; the reason goes in the audit log)</span></label>
                                <input id="fs-reason" type="text" name="reason" maxlength="255" value="{{ old('reason') }}" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                            </div>
                        @else
                            <span></span>
                        @endif
                        <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save fees</button>
                    </div>
                </form>

                <form method="POST" action="{{ route($schedule->isPublished() ? 'treasurer.fee-schedule.unpublish' : 'treasurer.fee-schedule.publish', $schedule) }}" class="mt-4 border-t border-neutral-200 pt-4">
                    @csrf
                    @if ($schedule->isPublished())
                        <button type="submit" class="w-full sm:w-auto border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-neutral-50">Unpublish (hide from families)</button>
                    @else
                        <button type="submit" class="w-full sm:w-auto border border-brand text-brand font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-soft">Publish to families</button>
                        <span class="block sm:inline sm:ml-3 mt-2 sm:mt-0 text-xs text-neutral-500">Save any changes first.</span>
                    @endif
                </form>
            @endunless
        </x-card>
    @endif
</x-app-layout>
