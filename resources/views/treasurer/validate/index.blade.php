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

        <div id="needs-match" class="bg-white rounded-2xl border border-neutral-200 px-5 py-5 sm:px-8 sm:py-6 scroll-mt-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-ink">Rows needing a match</h2>
                    <p class="text-sm text-neutral-500 mt-1">The student ID in the export doesn't exist in ISMS. Unknown students are never silently dropped — match each one or hold it.</p>
                </div>
                @if ($unmatchedRecords->isNotEmpty())
                    <span class="rounded-full bg-danger-soft text-danger px-3 py-1 text-sm font-semibold tabular-nums">{{ $unmatchedRecords->count() }} to go</span>
                @endif
            </div>

            {{-- Result of the last match / hold, shown where the user is working (also in the page header). --}}
            @foreach (['status' => ['check', 'bg-success-soft border-success-line text-success'], 'warning' => ['alert', 'bg-warning-soft border-warning-line text-warning']] as $key => [$icon, $tone])
                @if (session($key))
                    <div role="status" class="mt-4 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm {{ $tone }}">
                        <x-icon :name="$icon" class="w-5 h-5" /><p>{{ session($key) }}</p>
                    </div>
                @endif
            @endforeach

            @if ($pickerStudents->isNotEmpty())
                <datalist id="isms-students">
                    @foreach ($pickerStudents as $s)
                        <option value="{{ $s->student_id_number }}">{{ $s->name }}</option>
                    @endforeach
                </datalist>
            @endif

            <div class="mt-4 space-y-3">
                @forelse ($unmatchedRecords as $row)
                    @php
                        $rowErrors = $errors->getBag("resolve_{$row->id}");
                        $retry = session('resolve_row') === $row->id ? old('student', '') : '';
                    @endphp
                    <div id="match-{{ $row->id }}" x-data="{ id: @js($retry) }"
                         class="rounded-xl border p-4 scroll-mt-6 {{ $rowErrors->any() ? 'border-danger-line ring-2 ring-danger/20' : 'border-neutral-200' }}">
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
                                    <button type="button" @click="id = @js($candidate->student_id_number)"
                                            :aria-pressed="id === @js($candidate->student_id_number)"
                                            :class="id === @js($candidate->student_id_number) ? 'border-brand bg-brand-soft ring-1 ring-brand' : 'border-neutral-200 hover:border-brand'"
                                            class="rounded-lg border px-2.5 py-1 text-xs text-left transition-colors">
                                        <span class="font-semibold text-ink">{{ $candidate->name }}</span>
                                        <span class="text-neutral-500 tabular-nums">· {{ $candidate->student_id_number }}</span>
                                        @if (mb_strtolower($candidate->name) === mb_strtolower((string) $row->raw_student_name))
                                            <span class="ml-1 rounded bg-success-soft text-success px-1 font-bold">Same name</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-start gap-2">
                            <form method="POST" action="{{ route('treasurer.validate.resolve', $row) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <div>
                                    <label for="student-{{ $row->id }}" class="sr-only">ISMS student ID or full name</label>
                                    <input id="student-{{ $row->id }}" type="text" name="student" x-model="id" list="isms-students" autocomplete="off"
                                           placeholder="Student ID or full name…"
                                           @if ($rowErrors->any()) aria-invalid="true" aria-describedby="student-{{ $row->id }}-error" @endif
                                           class="w-64 rounded-lg border border-neutral-200 bg-white px-2.5 py-1.5 text-sm">
                                </div>
                                <button type="submit" :disabled="!id.trim()"
                                        class="text-sm font-semibold bg-brand text-white rounded-lg px-3 py-1.5 hover:bg-brand-dark transition-colors">Confirm match</button>
                            </form>
                            <form method="POST" action="{{ route('treasurer.validate.toggle-hold', $row) }}">
                                @csrf
                                <input type="hidden" name="from" value="match">
                                <button type="submit" class="text-sm font-semibold border border-neutral-300 text-neutral-700 rounded-lg px-3 py-1.5 hover:bg-neutral-50">Hold</button>
                            </form>
                        </div>
                        @if ($rowErrors->any())
                            <p id="student-{{ $row->id }}-error" role="alert" class="mt-2 flex items-start gap-1.5 text-sm text-danger">
                                <x-icon name="alert" class="w-4 h-4 mt-0.5" />{{ $rowErrors->first('student') }}
                            </p>
                        @else
                            <p class="mt-2 text-xs text-neutral-400">Pick a suggestion, or start typing a name or ID to search ISMS students.</p>
                        @endif
                    </div>
                @empty
                    <div class="flex items-center gap-2 rounded-xl bg-success-soft text-success px-4 py-3 text-sm font-semibold">
                        <x-icon name="check" class="w-5 h-5" /> Every row in this batch is matched or held.
                    </div>
                @endforelse
            </div>
        </div>

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
                                $manual = $row->student && $row->raw_student_key;
                                $issue = match (true) {
                                    $row->is_held => ['Held', 'bg-tint-teal text-tint-teal-ink'],
                                    ! $row->student => ['Not in ISMS', 'bg-danger-soft text-danger'],
                                    $row->has_name_conflict => ['Name conflict', 'bg-warning-soft text-warning'],
                                    $row->is_restricted => ['SDA allowance', 'bg-tint-purple text-tint-purple-ink'],
                                    $manual => ['Manually matched', 'bg-info-soft text-info'],
                                    default => ['Matched', 'bg-success-soft text-success'],
                                };
                            @endphp
                            <tr id="row-{{ $row->id }}" class="border-b border-neutral-100 last:border-0 scroll-mt-6 {{ $row->is_held ? 'opacity-60' : '' }} {{ session('matched_row') === $row->id ? 'bg-success-soft' : '' }}">
                                <td class="py-2 pl-5 pr-2">
                                    @if ($row->student)
                                        <p class="font-medium text-ink">{{ $row->student->name }}</p>
                                        <p class="text-xs text-neutral-400 tabular-nums">
                                            {{ $row->student->student_id_number }}
                                            @if ($row->has_name_conflict)<span class="text-warning">· export says “{{ $row->raw_student_name }}”</span>@endif
                                        </p>
                                        @if ($manual)<p class="text-[11px] text-neutral-400 tabular-nums">from source ID {{ $row->raw_student_key }}</p>@endif
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
                                    @if ($manual)
                                        <form method="POST" action="{{ route('treasurer.validate.unmatch', $row) }}" class="inline"
                                              onsubmit="return confirm(@js('Undo the match of '.$row->raw_student_key.' to '.$row->student->name.'?'));">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-danger hover:underline">Unmatch</button>
                                        </form>
                                    @endif
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
