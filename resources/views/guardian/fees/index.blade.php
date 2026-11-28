@use('App\Support\LedgerFormat')
@php $latest = $statements->first(); @endphp
<x-app-layout title="Fees" subtitle="Your child's fee account, from the school's finance office. Read-only." badge="Guardian · Read-only access" role="guardian">
    <x-child-switcher :children="$children" :child="$child" route="guardian.fees.index" />

    @include('fees.partials.summary', ['latest' => $latest, 'student' => $child, 'pdfUrl' => route('guardian.fees.statement', ['child' => $child->id])])

    @include('fees.partials.statements', ['statements' => $statements])

    @include('fee-schedule.partials.family-card', ['card' => $feeCard])

    <x-card title="Finance note">
        <p class="text-sm text-neutral-500">
            These are the school's accounts as last published by the finance office, so a recent payment may not show yet.
            Fees are due by the 15th of the month. If something looks wrong, please contact the Finance Office.
        </p>
    </x-card>
</x-app-layout>
