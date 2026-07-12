<x-app-layout title="Import Fee Records" subtitle="Upload the corrected Excel/CSV file prepared from the Sun account process." badge="Sun account, not Sun Plus" role="treasurer">
    <x-card title="Import behavior" subtitle="Designed for student-ID mismatch and duplicate prevention.">
        <div class="flex flex-wrap gap-2">
            <x-badge color="blue">Match by ISMS student ID (agreed key)</x-badge>
            <x-badge color="green">Re-upload updates existing records, no duplicates</x-badge>
            <x-badge color="yellow">Unknown students flagged, never dropped</x-badge>
            <x-badge color="blue">Every upload is an audited batch</x-badge>
        </div>
    </x-card>

    <x-card title="Upload corrected export" subtitle="The uploaded file is a record source only; it does not create transactions in ISMS.">
        <form method="POST" action="{{ route('treasurer.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Period</label>
                    <input type="text" name="period" required placeholder="Q2 2026" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">File (Excel / CSV)</label>
                    <input type="file" name="file" required accept=".xlsx,.xls,.csv" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>
            <p class="text-xs text-neutral-500">
                Expected columns: <code>student_id</code>, <code>date</code>, <code>amount</code>, <code>balance</code>,
                optionally <code>status</code> (Owed/Paid/Partial/Outstanding) and <code>restricted</code> (yes/no — SDA discount/allowance rows).
            </p>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Upload &amp; Validate</button>
        </form>
    </x-card>
</x-app-layout>
