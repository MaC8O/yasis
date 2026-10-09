<x-app-layout title="Source Prep" subtitle="Finance office workflow: how the source file gets from Sun account to a valid ISMS import." badge="Sun account, not Sun Plus" role="treasurer">
    <x-card title="Questionnaire-aligned finance workflow">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <x-badge color="yellow">Sun account finalized</x-badge>
            <span>&rarr;</span>
            <x-badge color="blue">Export Excel / Word adjusted</x-badge>
            <span>&rarr;</span>
            <x-badge color="yellow">Correct before upload</x-badge>
            <span>&rarr;</span>
            <x-badge color="blue">Treasurer upload</x-badge>
            <span>&rarr;</span>
            <x-badge color="green">Validate / match</x-badge>
            <span>&rarr;</span>
            <x-badge color="green">Publish records</x-badge>
        </div>
    </x-card>

    <x-card title="Finance office reality" subtitle="Built from the requirements questionnaire (§3.4).">
        <ul class="space-y-2 text-sm text-neutral-600 list-disc list-inside">
            <li>The school uses a Sun account process — not Sun Plus — for all accounting.</li>
            <li>Records are exported to Excel, with occasional manual Word adjustments before upload.</li>
            <li>Uploads follow the school's installment-payment cycle: quarterly, or on demand.</li>
            <li>Corrections are made in the source system and re-uploaded — never edited directly in ISMS.</li>
        </ul>
    </x-card>

    <x-card title="Before you upload — checklist">
        <ul class="space-y-2 text-sm text-neutral-600 list-disc list-inside">
            <li>Confirm the file has columns: student_id, student_name, date, amount, balance (description, status and restricted are optional).</li>
            <li>The student_id column should use the ISMS student ID format (e.g. YAS-2026-0001) — the agreed matching key.</li>
            <li>Keep each student's full name in student_name — it is checked against ISMS, so a mistyped ID shows up as a name conflict instead of posting to the wrong account.</li>
            <li>Use description for the ledger narrative (e.g. "Term 1 tuition", "Bus fee") — it appears on statements.</li>
            <li>Mark SDA discount/allowance rows with <code>restricted = yes</code> so they stay hidden from guardians/students.</li>
            <li>Double-check the period label (e.g. "Q2 2026") before uploading — it's how reports group collection rate.</li>
        </ul>
    </x-card>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('treasurer.import-template') }}" class="inline-block border border-brand text-brand font-semibold rounded-lg px-6 py-3 text-sm hover:bg-brand/5">Download import template (CSV)</a>
        <a href="{{ route('treasurer.import.index') }}" class="inline-block bg-brand text-white font-semibold rounded-lg px-6 py-3 text-sm hover:bg-brand-dark transition-colors">Go to Import Records</a>
    </div>
</x-app-layout>
