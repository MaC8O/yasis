{{-- The latest year's balance at the top of a family Fees page, with the statement PDF. $latest: SunFamilyStatement or null. --}}
@use('App\Support\LedgerFormat')
<div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden" data-fee-summary>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-5">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">{{ $latest ? 'Balance, '.$latest->content['year_label'] : 'Balance' }}</p>
            @if ($latest)
                @php $status = $latest->status(); @endphp
                <p class="text-3xl font-bold tabular-nums {{ ['Owing' => 'text-danger', 'Settled' => 'text-success', 'In credit' => 'text-brand'][$status] }}">{{ LedgerFormat::money($latest->closing_balance) }} <span class="text-sm font-semibold text-neutral-500">Ks</span></p>
                <p class="text-sm font-semibold">{{ $status === 'In credit' ? 'In credit: paid in advance or overpaid' : $status }}</p>
            @else
                <p class="text-lg text-neutral-500">No fee records published yet</p>
            @endif
        </div>
        @if ($latest)
            <a href="{{ $pdfUrl }}" target="_blank" class="inline-flex items-center justify-center gap-2 bg-brand text-white font-semibold rounded-lg px-4 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">
                <x-icon name="document" class="w-4 h-4" /> Download / print statement
            </a>
        @endif
    </div>
    <p class="px-5 py-3 border-t border-neutral-200 bg-neutral-50 text-xs text-neutral-500">Please quote student ID <strong class="tabular-nums">{{ $student->student_id_number }}</strong> when paying at the Finance Office.</p>
</div>
