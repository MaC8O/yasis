{{-- §10: this year's published fees for the child's grade, for guardians (and students in 8b). $card from FeeScheduleService::familyCard(). --}}
@use('App\Support\Money')
@if ($card)
    <x-card :title="$card['year'].' fees for '.$card['grade']" subtitle="The school's published fee schedule." data-fee-card>
        <dl class="divide-y divide-neutral-100 text-sm">
            @foreach ($card['lines'] as $type => $line)
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 py-3">
                    <dt>
                        <span class="font-semibold">{{ $line['label'] }}</span>
                        <span class="block text-xs text-neutral-500">
                            @if ($type === 'tuition')
                                For the school year{{ $line['instalments'] > 1 ? ', paid in '.$line['instalments'].' instalments' : '' }}
                            @else
                                One-time{{ $line['new_students_only'] ? ', new students only' : '' }}
                            @endif
                        </span>
                    </dt>
                    <dd class="sm:text-right">
                        <span class="font-bold tabular-nums">{{ Money::format($line['amount']) }} Ks</span>
                        @if ($type === 'tuition' && $line['instalments'] > 1)
                            @php $parts = $line['per_instalment']; @endphp
                            <span class="block text-xs text-neutral-500 tabular-nums">
                                {{ count(array_unique($parts)) === 1
                                    ? $line['instalments'].' instalments of '.Money::format($parts[0]).' Ks'
                                    : (count($parts) - 1).' instalments of '.Money::format($parts[0]).' Ks and a last of '.Money::format(end($parts)).' Ks' }}
                            </span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        <ul class="mt-3 list-disc pl-5 text-xs text-neutral-500 space-y-1">
            <li>Tuition is paid in instalments, starting when the child is first registered.</li>
            <li>Payment is preferably made at the school in cash, by MMQR, or by bank transfer to the school's accounts.</li>
        </ul>
    </x-card>
@endif
