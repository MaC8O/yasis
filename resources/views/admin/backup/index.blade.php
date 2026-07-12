<x-app-layout title="Data &amp; Backup" subtitle="Backup status, on-demand data snapshots, and data-retention actions." badge="Admin" role="admin">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Backup status" color="green">{{ $backupStatus }}</x-stat-tile>
        <x-stat-tile label="Last automated backup" color="blue">{{ $lastBackupAt ? \Carbon\Carbon::parse($lastBackupAt)->diffForHumans() : 'Infrastructure-managed' }}</x-stat-tile>
        <x-stat-tile label="Last data snapshot" color="pink">{{ $lastExportAt ? $lastExportAt->diffForHumans() : 'Never' }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="On-demand data snapshot" subtitle="A ZIP of CSV exports of the core tables — for an off-site copy or offline review. Passwords and tokens are never included.">
            <div class="space-y-1.5 mb-5">
                @foreach ($recordCounts as $label => $count)
                    <div class="flex items-center justify-between text-sm border-b border-neutral-100 last:border-0 py-1.5">
                        <span class="text-neutral-500">{{ $label }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($count) }}</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('admin.export-snapshot') }}"
               class="inline-block bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Generate &amp; download snapshot (ZIP)</a>
            <p class="text-xs text-neutral-400 mt-3">Includes {{ count($snapshotTables) }} tables. Last generated: {{ $lastExportAt ? $lastExportAt->format('M j, Y H:i') : 'never' }}.</p>
        </x-card>

        <x-card title="What's in a snapshot" subtitle="Tables exported as CSV inside the ZIP.">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($snapshotTables as $table)
                    <span class="inline-block rounded-md bg-neutral-100 text-neutral-600 text-xs font-medium px-2 py-1">{{ $table }}</span>
                @endforeach
            </div>
        </x-card>
    </div>

    <x-card title="Data retention &amp; erasure" subtitle="§6.2 — action an erasure request against a student or guardian. Records are anonymized, never hard-deleted; academic history is retained without PII. This action is audited.">
        <form method="POST" action="{{ route('admin.retention-actions.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Subject</label>
                <select name="subject_type" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="student">Student</option>
                    <option value="guardian">Guardian</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Identifier</label>
                <input type="text" name="identifier" placeholder="Student ID / guardian email" required
                       class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Reason</label>
                <input type="text" name="reason" maxlength="200" required
                       class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" onsubmit="return confirm('Erase and anonymize this record? This cannot be undone.');"
                    class="bg-red-700 text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Action erasure</button>
        </form>
    </x-card>

    <x-card title="Recent snapshot exports" subtitle="Audit trail of data snapshots generated.">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">When</th>
                    <th class="py-2 font-semibold">By</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lastExportEvents as $event)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $event->created_at->format('M j, Y H:i') }}</td>
                        <td class="py-2.5">{{ $event->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="py-4 text-neutral-400">No snapshots generated yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
