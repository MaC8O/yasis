<x-app-layout title="Source Prep" subtitle="How the school's Sun Accounts ledger gets into the ISMS. The file is imported as it comes out of Sun: there is nothing to convert." badge="Treasurer" role="treasurer">
    <x-card title="The steps">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <x-badge color="yellow">Print the Account Listing from Sun to a file</x-badge>
            <span>&rarr;</span>
            <x-badge color="blue">Upload it on Ledger Import</x-badge>
            <span>&rarr;</span>
            <x-badge color="yellow">Check the summary, then Import</x-badge>
            <span>&rarr;</span>
            <x-badge color="green">Match and review</x-badge>
            <span>&rarr;</span>
            <x-badge color="green">Publish</x-badge>
        </div>
    </x-card>

    <x-card title="Printing the listing">
        <ul class="space-y-2 text-sm text-neutral-600 list-disc list-inside">
            <li>In Sun Accounts, print the <strong>Account Listing</strong> report for the student accounts (the Student Control account and every student account) for one financial year, to a file (.PRN).</li>
            <li>Print it again whenever you want to update the ISMS during the year. Lines already imported are updated, new ones added, and anything changed is listed for you.</li>
            <li>Don't edit the file. The ISMS checks every running balance, every closing balance and the report total, and refuses a file that doesn't add up.</li>
            <li>Corrections are made in Sun and printed again, never typed into the ISMS.</li>
        </ul>
    </x-card>

    <x-card title="After importing">
        <ul class="space-y-2 text-sm text-neutral-600 list-disc list-inside">
            <li>Link each new account code to its student on Ledger Review. Names only suggest a student; the code is what matches.</li>
            <li>A linked code printed under a different name is held until you confirm whether it is the same student, since a code can pass to another student after one leaves.</li>
            <li>Families see every line once you publish, allowances included. Hide an exceptional line by hand on its account page; families then see it only inside "Adjustments".</li>
        </ul>
    </x-card>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('treasurer.ledger.index') }}" class="inline-flex items-center min-h-[44px] bg-brand text-white font-semibold rounded-lg px-6 py-3 text-sm hover:bg-brand-dark">Go to Ledger Import</a>
        <a href="{{ route('treasurer.info.visibility-rules') }}" class="inline-flex items-center min-h-[44px] border border-brand text-brand font-semibold rounded-lg px-6 py-3 text-sm hover:bg-brand/5">Who sees what</a>
    </div>
</x-app-layout>
