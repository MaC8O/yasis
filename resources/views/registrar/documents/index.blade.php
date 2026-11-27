<x-app-layout title="Transcripts & Certificates" subtitle="Prepare official academic documents and certificates for printing." badge="Registrar" role="registrar">
    <x-card title="Generate a document">
        <form method="POST" action="{{ route('registrar.documents.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            @csrf
            <div>
                <label for="doc-student" class="block text-sm font-semibold mb-1">Student ID</label>
                <input id="doc-student" type="text" name="student_id_number" required placeholder="YAS-2026-0001" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="doc-type" class="block text-sm font-semibold mb-1">Document type</label>
                <select id="doc-type" name="type" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark transition-colors">Generate Draft</button>
        </form>
        <p class="text-xs text-neutral-400 mt-3">Types: Transcript · Report Card · Transfer/Leaving · Completion · Enrollment (bonafide)</p>
    </x-card>

    <x-card title="Statement of Marks" subtitle="Made from the frozen year result, so it's available once the Principal has locked all four terms of the school year.">
        <form method="POST" action="{{ route('registrar.statements.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end" data-statement-form>
            @csrf
            <div>
                <label for="som-student" class="block text-sm font-semibold mb-1">Student ID</label>
                <input id="som-student" type="text" name="student_id_number" required value="{{ old('student_id_number') }}" placeholder="YAS-2026-0001" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="som-year" class="block text-sm font-semibold mb-1">School year</label>
                <select id="som-year" name="academic_year_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected((int) old('academic_year_id', $defaultYearId) === $year->id)>{{ $year->year_label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="som-exam" class="block text-sm font-semibold mb-1">Date of examination</label>
                <input id="som-exam" type="text" name="examination_date" required maxlength="60" value="{{ old('examination_date') }}" placeholder="MARCH 2-5, 2027" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label for="som-issue" class="block text-sm font-semibold mb-1">Date of issue</label>
                <input id="som-issue" type="date" name="issue_date" required value="{{ old('issue_date', today()->toDateString()) }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark transition-colors">Prepare statement</button>
        </form>
        <p class="text-xs text-neutral-500 mt-3">KG1 to Grade 9 and Grades 11-12 have a statement; Grade 10 has none until the school answers Q11. A student whose year result is incomplete gets none, and you'll see why.</p>
        @unless ($issuanceEnabled)
            <p class="mt-2 text-sm text-warning" data-issuance-off>{{ $issuanceOff }} You can prepare and preview statements now.</p>
        @endunless
    </x-card>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Queued (draft)" color="yellow">{{ $stats['queued'] }}</x-stat-tile>
        <x-stat-tile label="Ready to print" color="green">{{ $stats['readyToPrint'] }}</x-stat-tile>
        <x-stat-tile label="Printed" color="blue">{{ $stats['printed'] }}</x-stat-tile>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 pr-3 font-semibold">Student</th>
                        <th class="py-2 pr-3 font-semibold">Document</th>
                        <th class="py-2 pr-3 font-semibold">Status</th>
                        <th class="py-2 pr-3 font-semibold hidden sm:table-cell">Prepared by</th>
                        <th class="py-2 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $doc)
                        @php $statement = $doc->statement; @endphp
                        <tr class="border-b border-neutral-100 last:border-0 align-top" @if ($statement) data-statement="{{ $statement->id }}" @endif>
                            <td class="py-2.5 pr-3">{{ $doc->student->name }}</td>
                            <td class="py-2.5 pr-3">
                                {{ $doc->type }}
                                @if ($statement)
                                    <span class="block text-xs text-neutral-500">{{ $statement->academicYear->year_label }} · examination {{ $statement->examination_date }} · issue {{ $statement->issue_date->format('j M Y') }}</span>
                                    @if (in_array($doc->id, $outdated))
                                        <span class="block text-xs text-warning" data-outdated>The year's results were re-frozen after this was issued. Prepare a new statement.</span>
                                    @endif
                                @endif
                            </td>
                            <td class="py-2.5 pr-3"><x-badge :color="in_array($doc->status, ['Printed', 'Ready']) ? 'blue' : (in_array($doc->status, ['Pending Approval', 'Approved']) ? 'yellow' : 'green')">{{ $statement && $statement->isIssued() ? 'Issued' : $doc->status }}</x-badge></td>
                            <td class="py-2.5 pr-3 text-neutral-500 hidden sm:table-cell">{{ $doc->preparedBy->user->name ?? '—' }}</td>
                            <td class="py-2.5 text-right">
                                @if ($statement)
                                    <div class="flex flex-wrap justify-end items-center gap-x-3 gap-y-1">
                                        @unless ($statement->isIssued())
                                            <a href="{{ route('registrar.statements.preview', $statement) }}" target="_blank" class="text-xs font-semibold text-brand hover:underline">Preview draft</a>
                                        @endunless
                                        <form method="POST" action="{{ route('registrar.statements.print', $statement) }}" target="_blank" class="inline" onsubmit="setTimeout(() => location.reload(), 2000)">
                                            @csrf
                                            @if ($issuanceEnabled)
                                                <button type="submit" class="text-xs font-semibold text-brand hover:underline">{{ $statement->isIssued() ? 'Re-print' : 'Print (issue)' }}</button>
                                            @else
                                                <button type="submit" disabled title="{{ $issuanceOff }}" class="text-xs font-semibold text-neutral-400 cursor-not-allowed">{{ $statement->isIssued() ? 'Re-print' : 'Print (issue)' }}</button>
                                            @endif
                                        </form>
                                    </div>
                                    @unless ($statement->isIssued())
                                        <details class="mt-1 text-left">
                                            <summary class="text-xs font-semibold text-brand cursor-pointer text-right">Change dates</summary>
                                            <form method="POST" action="{{ route('registrar.statements.update', $statement) }}" class="mt-2 grid gap-2">
                                                @csrf @method('PUT')
                                                <label class="text-xs font-semibold">Date of examination
                                                    <input type="text" name="examination_date" required maxlength="60" value="{{ $statement->examination_date }}" class="mt-0.5 w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-sm font-normal">
                                                </label>
                                                <label class="text-xs font-semibold">Date of issue
                                                    <input type="date" name="issue_date" required value="{{ $statement->issue_date->toDateString() }}" class="mt-0.5 w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-sm font-normal">
                                                </label>
                                                <button type="submit" class="justify-self-end bg-brand text-white font-semibold rounded-lg px-3 py-1.5 text-xs min-h-[36px]">Save dates</button>
                                            </form>
                                        </details>
                                    @endunless
                                @elseif ($doc->type === 'Transcript' && $doc->status === 'Draft')
                                    <form method="POST" action="{{ route('registrar.documents.submit-for-approval', $doc) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-brand hover:underline">Submit for approval</button>
                                    </form>
                                @elseif ($doc->type === 'Transcript' && ! in_array($doc->status, ['Ready', 'Printed']))
                                    <span class="text-xs text-neutral-400">Awaiting two-key approval</span>
                                @else
                                    <a href="{{ route('registrar.documents.download', $doc) }}" target="_blank" class="text-xs font-semibold text-brand hover:underline">
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
        </div>
        <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
            <x-per-page />
            <div>{{ $documents->links() }}</div>
        </div>
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
        <p class="text-sm text-neutral-500 mt-2">
            A Statement of Marks prints only while the Principal's "Transcript & certificate issuance" control is on.
            Its first print issues it: the statement is kept exactly as printed, and every reprint is that copy.
        </p>
    </x-card>
</x-app-layout>
