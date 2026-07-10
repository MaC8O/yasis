<x-app-layout title="Bulk Import Students" subtitle="Register many students at once from an Excel/CSV export." badge="Registrar" role="registrar">
    <x-card title="Import behavior" subtitle="Designed for onboarding a class list or migrating legacy paper/Excel records in one pass.">
        <div class="flex flex-wrap gap-2">
            <x-badge color="blue">Matches department by name (required)</x-badge>
            <x-badge color="green">Existing student IDs are skipped, never duplicated</x-badge>
            <x-badge color="yellow">Section &amp; guardian columns are optional</x-badge>
            <x-badge color="blue">Every import is audit-logged</x-badge>
        </div>
    </x-card>

    <x-card title="Upload student list" subtitle="Rows with a missing student ID, name, or unrecognized department are reported and skipped — nothing partial is saved for that row.">
        <form method="POST" action="{{ route('registrar.students.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">File (Excel / CSV)</label>
                <input type="file" name="file" required accept=".xlsx,.xls,.csv" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <p class="text-xs text-neutral-500">
                Required columns: <code>student_id_number</code>, <code>first_name</code>, <code>last_name</code>, <code>department</code>.
                Optional: <code>section</code>, <code>date_of_birth</code>, <code>gender</code>, <code>religious_background</code>, <code>admission_date</code>,
                <code>guardian_name</code>, <code>guardian_email</code>, <code>guardian_relationship</code>, <code>guardian_phone</code>.
            </p>
            <div class="flex gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Upload &amp; Import</button>
                <a href="{{ route('registrar.students.import.template') }}" class="text-sm font-semibold text-neutral-600 self-center hover:underline">Download CSV template</a>
                <a href="{{ route('registrar.students.index') }}" class="text-sm font-semibold text-neutral-500 self-center hover:underline">Back to student records</a>
            </div>
        </form>
    </x-card>

    @if (session('importResults'))
        @php $results = session('importResults'); @endphp
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <x-card title="Created ({{ count($results['created']) }})">
                @forelse ($results['created'] as $line)
                    <p class="text-sm text-green-700 py-1 border-b border-neutral-100 last:border-0">{{ $line }}</p>
                @empty
                    <p class="text-sm text-neutral-400">No rows created.</p>
                @endforelse
            </x-card>
            <x-card title="Skipped — duplicates ({{ count($results['skipped']) }})">
                @forelse ($results['skipped'] as $line)
                    <p class="text-sm text-yellow-700 py-1 border-b border-neutral-100 last:border-0">{{ $line }}</p>
                @empty
                    <p class="text-sm text-neutral-400">No duplicates skipped.</p>
                @endforelse
            </x-card>
            <x-card title="Errors ({{ count($results['errors']) }})">
                @forelse ($results['errors'] as $line)
                    <p class="text-sm text-red-700 py-1 border-b border-neutral-100 last:border-0">{{ $line }}</p>
                @empty
                    <p class="text-sm text-neutral-400">No errors.</p>
                @endforelse
            </x-card>
        </div>
    @endif
</x-app-layout>
