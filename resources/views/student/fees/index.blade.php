<x-app-layout title="Fees" subtitle="Your fee account, from the school's finance office. Read-only." :badge="$student->name" role="student">
    @include('fees.partials.summary', ['latest' => $statements->first(), 'student' => $student, 'pdfUrl' => route('student.fees.statement')])

    @include('fees.partials.statements', ['statements' => $statements])

    @include('fee-schedule.partials.family-card', ['card' => $feeCard])

    <x-card title="Finance note">
        <p class="text-sm text-neutral-500">
            These are the school's accounts as last published by the finance office, so a recent payment may not show yet.
            Fees are due by the 15th of the month. Questions go to the Finance Office.
        </p>
    </x-card>
</x-app-layout>
