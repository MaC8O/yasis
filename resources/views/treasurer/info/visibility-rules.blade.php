<x-app-layout title="Visibility Rules" subtitle="Who can see imported fee records, and what's hidden." badge="No transactions" role="treasurer">
    <x-card title="Record rule">
        <p class="text-sm text-neutral-600">
            Guardians and students see amounts owed, paid, partial, and outstanding for their own child/record only.
            <strong>SDA student discounts and allowances are hidden</strong> from the guardian/student view — these
            rows are marked <code>restricted</code> at import time and excluded from anything they can see.
        </p>
    </x-card>

    <x-card title="Access by role">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Role</th>
                    <th class="py-2 font-semibold">Access</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-neutral-100">
                    <td class="py-2.5 font-semibold">Treasurer</td>
                    <td class="py-2.5">Full import data — matched, unmatched, and restricted rows. Owns import, matching, and reverting.</td>
                </tr>
                <tr class="border-b border-neutral-100">
                    <td class="py-2.5 font-semibold">Principal / VP Academic / Registrar</td>
                    <td class="py-2.5">Read-only visibility of published, matched records. No editing, importing, or reverting.</td>
                </tr>
                <tr>
                    <td class="py-2.5 font-semibold">Guardian / Student</td>
                    <td class="py-2.5">Own-record only, from published batches. Restricted (SDA) rows are hidden entirely.</td>
                </tr>
            </tbody>
        </table>
    </x-card>

    <x-card title="Boundary with the school's accounting system">
        <p class="text-sm text-neutral-600">
            The ISMS does not record payments, calculate fees, generate invoices, or transfer data back to the
            accounting system. It only displays records the finance office has already finalized and exported.
        </p>
    </x-card>
</x-app-layout>
