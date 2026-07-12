<x-app-layout title="Bulk Import Users" subtitle="Create many login accounts at once from an Excel/CSV list — e.g. onboarding the teaching staff." badge="Admin" role="admin">
    <x-card title="Import behavior">
        <div class="flex flex-wrap gap-2">
            <x-badge color="blue">Staff roles get a staff profile automatically</x-badge>
            <x-badge color="green">Existing emails and staff IDs are skipped, never duplicated</x-badge>
            <x-badge color="yellow">Each new account receives a login-setup email</x-badge>
            <x-badge color="blue">Every import is audit-logged</x-badge>
        </div>
    </x-card>

    <x-card title="Upload user list" subtitle="Accounts are created with Pending status; they become Active when the user sets their password from the emailed link.">
        <form method="POST" action="{{ route('admin.users.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">File (Excel / CSV)</label>
                <input type="file" name="file" required accept=".xlsx,.xls,.csv" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <p class="text-xs text-neutral-500">
                Required columns: <code>name</code>, <code>email</code>, <code>role</code>
                (admin / principal / vp_academic / registrar / teacher / treasurer / hr_office / guardian / student).
                Staff roles also require <code>staff_id_number</code>.
                Optional: <code>department</code>, <code>joined_date</code>, <code>date_of_birth</code>, <code>gender</code>, <code>phone</code>, <code>address</code>.
            </p>
            <div class="flex gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Upload &amp; Import</button>
                <a href="{{ route('admin.users.import.template') }}" class="text-sm font-semibold text-neutral-600 self-center hover:underline">Download CSV template</a>
                <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-neutral-500 self-center hover:underline">Back to user management</a>
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
                    <p class="text-sm text-neutral-400">No accounts created.</p>
                @endforelse
            </x-card>
            <x-card title="Skipped — duplicates ({{ count($results['skipped']) }})">
                @forelse ($results['skipped'] as $line)
                    <p class="text-sm text-yellow-700 py-1 border-b border-neutral-100 last:border-0">{{ $line }}</p>
                @empty
                    <p class="text-sm text-neutral-400">No duplicates skipped.</p>
                @endforelse
            </x-card>
            <x-card title="Issues ({{ count($results['errors']) }})">
                @forelse ($results['errors'] as $line)
                    <p class="text-sm text-red-700 py-1 border-b border-neutral-100 last:border-0">{{ $line }}</p>
                @empty
                    <p class="text-sm text-neutral-400">No issues.</p>
                @endforelse
            </x-card>
        </div>
    @endif
</x-app-layout>
