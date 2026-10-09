@use('App\Support\Money')
<x-app-layout title="Fee Status" subtitle="Your child's fee account, from the school finance office." badge="Guardian · Read-only access" role="guardian">
    <x-child-switcher :children="$children" :child="$child" route="guardian.fees.index" />

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-4 px-5 py-5">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Still owed</p>
                <p class="text-3xl font-bold tabular-nums {{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ Money::format($balance) }} <span class="text-sm font-semibold text-neutral-500">{{ Money::CURRENCY }}</span></p>
                @if ($lines->isNotEmpty())<x-finance.status-pill :status="$status" class="mt-1" />@endif
            </div>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm sm:text-right self-center">
                <dt class="text-neutral-500">Total charged</dt><dd class="font-semibold tabular-nums">{{ Money::format($totalBilled) }}</dd>
                <dt class="text-neutral-500">Paid</dt><dd class="font-semibold tabular-nums text-success">{{ Money::format($paid) }}</dd>
            </dl>
        </div>
        <div class="flex flex-wrap items-center gap-3 px-5 py-3 border-t border-neutral-200 bg-neutral-50">
            <a href="{{ route('guardian.fees.statement', ['child' => $child->id]) }}" target="_blank" class="inline-flex items-center gap-2 bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">
                <x-icon name="document" class="w-4 h-4" /> Download / print statement
            </a>
            <p class="text-xs text-neutral-500">Please quote account <strong class="tabular-nums">{{ $child->student_id_number }}</strong> when paying at the Finance Office.</p>
        </div>
    </div>

    @if ($balance > 0)
        <x-card title="How long the balance has been due">
            <x-finance.aging-strip :aging="$aging" />
        </x-card>
    @endif

    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-neutral-200">
            <h2 class="text-lg font-bold text-ink">Statement</h2>
            <p class="text-sm text-neutral-500">Charges and payments as recorded by the finance office.</p>
        </div>
        <x-finance.statement :lines="$lines" />
    </div>

    <x-card title="Finance note">
        <p class="text-sm text-neutral-500">
            The portal displays records from the school's finance office. Payments are recorded there and appear here
            after the next update. If something looks wrong, please contact the Finance Office.
        </p>
    </x-card>
</x-app-layout>
