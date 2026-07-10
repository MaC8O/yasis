<x-app-layout title="Transcripts & Certificates" subtitle="Prepare official academic documents and certificates for printing." badge="Registrar" role="registrar">
    <x-card title="Generate a document">
        <form method="POST" action="{{ route('registrar.documents.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Student ID</label>
                <input type="text" name="student_id_number" required placeholder="YAS-2026-0001" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Document type</label>
                <select name="type" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Generate Draft</button>
        </form>
        <p class="text-xs text-neutral-400 mt-3">Types: Transcript · Report Card · Transfer/Leaving · Completion · Enrollment (bonafide)</p>
    </x-card>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Queued (draft)" color="yellow">{{ $stats['queued'] }}</x-stat-tile>
        <x-stat-tile label="Ready to print" color="green">{{ $stats['readyToPrint'] }}</x-stat-tile>
        <x-stat-tile label="Printed" color="blue">{{ $stats['printed'] }}</x-stat-tile>
    </div>

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Student</th>
                    <th class="py-2 font-semibold">Document</th>
                    <th class="py-2 font-semibold">Status</th>
                    <th class="py-2 font-semibold">Prepared by</th>
                    <th class="py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $doc)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $doc->student->first_name }} {{ $doc->student->last_name }}</td>
                        <td class="py-2.5">{{ $doc->type }}</td>
                        <td class="py-2.5"><x-badge :color="in_array($doc->status, ['Printed', 'Ready']) ? 'blue' : (in_array($doc->status, ['Pending Approval', 'Approved']) ? 'yellow' : 'green')">{{ $doc->status }}</x-badge></td>
                        <td class="py-2.5 text-neutral-500">{{ $doc->preparedBy->user->name ?? '—' }}</td>
                        <td class="py-2.5 text-right">
                            @if ($doc->type === 'Transcript' && $doc->status === 'Draft')
                                <form method="POST" action="{{ route('registrar.documents.submit-for-approval', $doc) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-[#1F573D] hover:underline">Submit for approval</button>
                                </form>
                            @elseif ($doc->type === 'Transcript' && ! in_array($doc->status, ['Ready', 'Printed']))
                                <span class="text-xs text-neutral-400">Awaiting two-key approval</span>
                            @else
                                <a href="{{ route('registrar.documents.download', $doc) }}" target="_blank" class="text-xs font-semibold text-[#1F573D] hover:underline">
                                    {{ $doc->status === 'Printed' ? 'Re-print PDF' : 'Generate PDF' }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-neutral-400">No documents requested yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $documents->links() }}</div>
    </x-card>

    <x-card title="Approval workflow">
        <div class="flex gap-3 flex-wrap">
            <x-badge color="blue">Registrar prepares</x-badge>
            <x-badge color="yellow">Principal / VP approves (governance control, later phase)</x-badge>
            <x-badge color="green">PDF print / export</x-badge>
        </div>
        <p class="text-sm text-neutral-500 mt-3">
            Enrollment, transfer/leaving, and completion certificates are Registrar-issued directly. Term transcripts
            are additionally gated by the Principal's "enable transcript issuance" governance control once the
            Principal portal lands.
        </p>
    </x-card>
</x-app-layout>
