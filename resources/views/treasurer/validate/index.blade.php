@use('App\Support\Money')
<x-app-layout title="Validate & Match Import" subtitle="Resolve unmatched rows and publish before they're visible to leadership and guardians." badge="Sun account, not Sun Plus" role="treasurer">
    <x-card>
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label for="batch" class="block text-sm font-semibold mb-1">Batch</label>
                <select id="batch" name="batch" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($batches as $b)
                        <option value="{{ $b->id }}" @selected($batch && $b->id === $batch->id)>{{ $b->period }} — {{ $b->source_file }} ({{ $b->is_published ? 'Published' : 'Draft' }})</option>
                    @endforeach
                </select>
            </div>
        </form>
    </x-card>

    @if ($batch)
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <x-stat-tile label="Uploaded rows" color="blue">{{ $uploaded }}</x-stat-tile>
            <x-stat-tile label="Matched" color="green">{{ $matched }}</x-stat-tile>
            <x-stat-tile label="Not in ISMS" color="pink">{{ $unmatchedRecords->count() }}</x-stat-tile>
            <x-stat-tile label="Name conflicts" color="yellow">{{ $nameConflicts }}</x-stat-tile>
            <x-stat-tile label="Restricted (SDA)" color="purple">{{ $restrictedCount }}</x-stat-tile>
            <x-stat-tile label="Held" color="teal">{{ $heldCount }}</x-stat-tile>
        </div>

        <x-card title="Rows needing a match" subtitle="The student ID in the export doesn't exist in ISMS. Unknown students are never silently dropped — match each one or hold it.">
            <div class="space-y-3">
                @forelse ($unmatchedRecords as $row)
                    <div class="rounded-xl border border-neutral-200 p-4" x-data="{ id: '' }">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-ink">{{ $row->raw_student_name ?: 'No name in export' }}</p>
                                <p class="text-xs text-neutral-500 tabular-nums">Source ID {{ $row->raw_student_key ?: '—' }} · {{ $row->txn_date->format('d M Y') }} · {{ $row->description ?: 'Fee charge' }}</p>
                            </div>
                            <p class="text-sm tabular-nums text-right">
                                <span class="text-neutral-500">Charge</span> <strong>{{ Money::format($row->amount) }}</strong>
                                <span class="text-neutral-500 ml-2">Balance</span> <strong>{{ Money::format($row->balance) }}</strong>
                            </p>
                        </div>

                        @if (($suggestions[$row->id] ?? collect())->isNotEmpty())
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-neutral-500">Suggested:</span>
                                @foreach ($suggestions[$row->id] as $candidate)
                                    <button type="button" @click="id = '{{ $candidate->student_id_number }}'"
                                            :class="id === '{{ $candidate->student_id_number }}' ? 'border-brand bg-brand-soft' : 'border-neutral-200 hover:border-brand'"
                                            class="rounded-lg border px-2.5 py-1 text-xs text-left transition-colors">
                                        <span class="font-semibold text-ink">{{ $candidate->name }}</span>
                                        <span class="text-neutral-500 tabular-nums">· {{ $candidate->student_id_number }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('treasurer.validate.resolve', $row) }}" class="flex gap-2">
                                @csrf
                                <label for="match-{{ $row->id }}" class="sr-only">ISMS student ID</label>
                                <input id="match-{{ $row->id }}" type="text" name="student_id_number" x-model="id" placeholder="YAS-2026-0001" required
                                       class="w-44 rounded-lg border border-neutral-200 bg-neutral-50 px-2.5 py-1.5 text-xs tabular-nums">
                                <button type="submit" class="text-xs font-semibold bg-brand text-white rounded-lg px-3 py-1.5 hover:bg-brand-dark transition-colors">Confirm match</button>
                            </form>
                            <form method="POST" action="{{ route('treasurer.validate.toggle-hold', $row) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold border border-neutral-300 text-neutral-700 rounded-lg px-3 py-1.5 hover:bg-neutral-50">Hold</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">Every row in this batch is matched or held.</p>
                @endforelse
            </div>
        </x-card>

        <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-neutral-200">
                <h2 class="text-lg font-bold text-ink">All rows</h2>
                <p class="text-sm text-neutral-500 mt-1">Restrict hides a row from guardians (SDA allowance); Hold parks it out of publishing.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-neutral-500 border-b-2 border-neutral-300 bg-neutral-50">
                            <th class="py-2 pl-5 pr-2 text-left font-semibold">Student</th>
                            <th class="py-2 px-2 text-left font-semibold">Date</th>
                            <th class="py-2 px-2 text-left font-semibold">Description</th>
                            <th class="py-2 px-2 text-right font-semibold">Charge</th>
                            <th class="py-2 px-2 text-right font-semibold">Balance</th>
                            <th class="py-2 px-2 text-left font-semibold">Status</th>
                            <th class="py-2 px-2 text-left font-semibold">Issue</th>
                            <th class="py-2 pl-2 pr-5 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $row)
                            @php
                                $issue = match (true) {
                                    $row->is_held => ['Held', 'bg-tint-teal text-tint-teal-ink'],
                                    ! $row->student => ['Not in ISMS', 'bg-danger-soft text-danger'],
                                    $row->has_name_conflict => ['Name conflict', 'bg-warning-soft text-warning'],
                                    $row->is_restricted => ['SDA allowance', 'bg-tint-purple text-tint-purple-ink'],
                                    default => ['Matched', 'bg-success-soft text-success'],
                                };
                            @endphp
                            <tr class="border-b border-neutral-100 last:border-0 {{ $row->is_held ? 'opacity-60' : '' }}">
                                <td class="py-2 pl-5 pr-2">
                                    @if ($row->student)
                                        <p class="font-medium text-ink">{{ $row->student->name }}</p>
                                        <p class="text-xs text-neutral-400 tabular-nums">
                                            {{ $row->student->student_id_number }}
                                            @if ($row->has_name_conflict)<span class="text-warning">· export says “{{ $row->raw_student_name }}”</span>@endif
                                        </p>
                                    @else
                                        <p class="font-medium text-neutral-600">{{ $row->raw_student_name ?: '—' }}</p>
                                        <p class="text-xs text-neutral-400 tabular-nums">{{ $row->raw_student_key ?: '—' }}</p>
                                    @endif
                                </td>
                                <td class="py-2 px-2 whitespace-nowrap">{{ $row->txn_date->format('d M Y') }}</td>
                                <td class="py-2 px-2 text-neutral-600">{{ $row->description ?: '—' }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ Money::format($row->amount) }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ Money::format($row->balance) }}</td>
                                <td class="py-2 px-2"><x-finance.status-pill :status="$row->status" /></td>
                                <td class="py-2 px-2"><span class="rounded-md px-2 py-0.5 text-xs font-semibold whitespace-nowrap {{ $issue[1] }}">{{ $issue[0] }}</span></td>
                                <td class="py-2 pl-2 pr-5 text-right whitespace-nowrap space-x-3">
                                    <form method="POST" action="{{ route('treasurer.validate.toggle-restrict', $row) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-info hover:underline">{{ $row->is_restricted ? 'Unrestrict' : 'Restrict' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('treasurer.validate.toggle-hold', $row) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-warning hover:underline">{{ $row->is_held ? 'Release' : 'Hold' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-6 px-5 text-neutral-400">No rows in this batch.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-card title="Publish">
            @if ($batch->is_published)
                <p class="text-sm"><x-badge color="green">Published {{ $batch->published_at->format('d M Y, H:i') }}</x-badge></p>
                @unless ($batch->is_restricted_confirmed)
                    <p class="text-sm text-warning font-semibold mt-3">Restricted rows changed since publishing — <a href="{{ route('treasurer.info.visibility-rules') }}" class="underline">re-confirm the classification</a>.</p>
                @endunless
            @elseif ($blockingCount > 0)
                <p class="text-sm text-danger mb-2 font-semibold">{{ $blockingCount }} unmatched row(s) block publishing.</p>
                <p class="text-sm text-neutral-500">Match each row to a student, or put it on hold to park it out of this publish.</p>
            @else
                <form method="POST" action="{{ route('treasurer.validate.publish', $batch) }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-neutral-500">
                        Publishing makes matched rows visible to leadership (read-only) and guardians, following the
                        <a href="{{ route('treasurer.info.visibility-rules') }}" class="font-semibold text-brand hover:underline">visibility rules</a>.
                        @if ($heldCount > 0) {{ $heldCount }} held row(s) stay excluded until released. @endif
                    </p>
                    <label class="flex items-start gap-2.5 rounded-xl border border-tint-purple bg-tint-purple/30 p-3 text-sm">
                        <input type="checkbox" name="confirm_restricted" value="1" required class="mt-0.5">
                        <span>
                            I've checked the <strong>{{ $restrictedCount }}</strong> restricted (SDA) {{ Str::plural('row', $restrictedCount) }} in this batch.
                            Restricted rows are never shown to guardians.
                        </span>
                    </label>
                    <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-6 py-3 text-sm hover:bg-brand-dark transition-colors">Publish batch</button>
                </form>
            @endif
        </x-card>
    @else
        <x-card><p class="text-sm text-neutral-400">No import batches yet. Start from Import Records.</p></x-card>
    @endif
</x-app-layout>
