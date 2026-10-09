{{--
    Student detail panel (master-detail on Student Records). Rendered inline when the page is
    opened with ?selected= and re-fetched from registrar.students.panel when a row is clicked.
--}}
@php
    $initials = collect(explode(' ', $student->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $statusColor = $student->enrollment_status === 'Enrolled' ? 'green' : ($student->enrollment_status === 'Graduated' ? 'blue' : 'pink');
    $primary = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
    $guardianPhone = $primary?->phone ?? $primary?->user?->phone;
    $rate = $attendanceTotal
        ? (($attendance['Present'] ?? 0) + ($attendance['Tardy'] ?? 0) + ($attendance['Excused'] ?? 0)) / $attendanceTotal * 100
        : null;

    $circle = 'w-9 h-9 rounded-full border border-white/40 flex items-center justify-center transition-colors group-hover:bg-white group-hover:text-brand';
    $tile = 'group flex flex-col items-center gap-1 w-14 text-white text-[11px] font-medium';
    $off = 'flex flex-col items-center gap-1 w-14 text-white/35 text-[11px] font-medium cursor-not-allowed';
    $dl = 'divide-y divide-neutral-100 text-sm';
    $dt = 'text-neutral-500 shrink-0';
    $dd = 'text-right text-ink font-medium min-w-0 break-words';
    $group = 'px-4 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-neutral-400 bg-neutral-50 border-y border-neutral-100';
@endphp

<section aria-label="Student information" x-data="{ tab: 'profile' }"
         class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">

    {{-- Panel title bar --}}
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-neutral-200 bg-neutral-50">
        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-500">
            <x-icon name="info" class="w-4 h-4" /> Information
        </p>
        <span class="text-[11px] text-neutral-400 tabular-nums">{{ $student->student_id_number }}</span>
    </div>

    {{-- Command band: class block + quick actions --}}
    <div class="grid grid-cols-[80px_1fr]">
        <div class="bg-gold text-ink flex flex-col items-center justify-center text-center px-2 py-4">
            <x-icon name="student" class="w-5 h-5 opacity-70" />
            {{-- Non-breaking hyphen so "Grade 9-A" never splits at the dash in this narrow block. --}}
            <p class="mt-1.5 text-sm font-bold leading-tight">{{ str_replace('-', "\u{2011}", $currentEnrollment?->section?->name ?? 'No class') }}</p>
        </div>
        <div class="bg-brand px-1 py-3 flex flex-wrap items-start justify-around gap-y-2">
            <a href="{{ route('registrar.students.edit', $student) }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="pencil" class="w-4 h-4" /></span> Edit
            </a>

            <a href="{{ route('registrar.guardians.index', ['student' => $student->id]) }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="users" class="w-4 h-4" /></span> Guardians
            </a>

            @if ($primary?->user?->email)
                <a href="mailto:{{ $primary->user->email }}" class="{{ $tile }}" title="Email {{ $primary->user->name }}">
                    <span class="{{ $circle }}"><x-icon name="mail" class="w-4 h-4" /></span> Email
                </a>
            @else
                <span class="{{ $off }}" title="No guardian email on file">
                    <span class="w-9 h-9 rounded-full border border-white/20 flex items-center justify-center"><x-icon name="mail" class="w-4 h-4" /></span> Email
                </span>
            @endif

            @if ($guardianPhone)
                <a href="tel:{{ $guardianPhone }}" class="{{ $tile }}" title="Call {{ $primary->user?->name }}">
                    <span class="{{ $circle }}"><x-icon name="phone" class="w-4 h-4" /></span> Call
                </a>
            @else
                <span class="{{ $off }}" title="No guardian phone on file">
                    <span class="w-9 h-9 rounded-full border border-white/20 flex items-center justify-center"><x-icon name="phone" class="w-4 h-4" /></span> Call
                </span>
            @endif

            <a href="{{ route('registrar.documents.index') }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="document" class="w-4 h-4" /></span> Documents
            </a>
        </div>
    </div>

    {{-- Status strip --}}
    <div class="flex items-center justify-between gap-3 bg-tint-yellow text-tint-yellow-ink text-xs px-4 py-1.5">
        <span class="flex items-center gap-1.5">
            <x-icon name="chart" class="w-3.5 h-3.5" />
            @if ($rate !== null)
                Attendance this year <strong class="tabular-nums">{{ round($rate) }}%</strong>
            @else
                No attendance recorded this year
            @endif
        </span>
        @if ($student->guardians->isEmpty())
            <span class="font-semibold text-danger">No guardian linked</span>
        @endif
    </div>

    {{-- Identity --}}
    <div class="flex items-center gap-4 px-4 py-4 border-b border-neutral-200">
        <a href="{{ route('registrar.students.edit', $student) }}" class="group relative shrink-0 rounded-full" title="Change photo">
            @if ($student->photo_path)
                <img src="{{ Storage::url($student->photo_path) }}" alt="" class="w-20 h-20 rounded-full object-cover border border-neutral-200">
            @else
                <span class="w-20 h-20 rounded-full bg-neutral-100 border border-neutral-200 text-neutral-500 font-bold text-2xl flex items-center justify-center">{{ $initials }}</span>
            @endif
            <span class="absolute inset-0 rounded-full bg-ink/55 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition-opacity">
                <x-icon name="camera" class="w-5 h-5" />
            </span>
        </a>
        <div class="min-w-0 flex-1">
            <p class="text-lg font-bold text-ink leading-tight truncate">{{ $student->name }}</p>
            <p class="mt-1 text-base font-bold text-ink tabular-nums">{{ $student->student_id_number }}</p>
            <p class="text-sm text-neutral-600 truncate">{{ $student->department?->name ?? '—' }}</p>
        </div>
        <x-badge :color="$statusColor" class="shrink-0 self-start">{{ $student->enrollment_status }}</x-badge>
    </div>

    {{-- Tabs --}}
    <div role="tablist" aria-label="Student details" class="flex border-b border-neutral-200 px-2 text-xs font-bold uppercase tracking-wide">
        @foreach (['profile' => 'Profile', 'attendance' => 'Attendance', 'documents' => 'Documents'] as $key => $label)
            <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'text-brand border-brand' : 'text-neutral-400 border-transparent hover:text-neutral-700'"
                    class="px-3 py-2.5 border-b-2 -mb-px transition-colors">
                {{ $label }}
                @if ($key === 'documents' && $student->documentRequests->isNotEmpty())
                    <span class="ml-1 rounded-full bg-neutral-100 text-neutral-600 px-1.5 py-0.5 text-[10px] tabular-nums">{{ $student->documentRequests->count() }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Profile --}}
    <div x-show="tab === 'profile'" role="tabpanel" class="max-h-[460px] overflow-y-auto">
        <p class="{{ $group }} border-t-0">Personal</p>
        <dl class="{{ $dl }}">
            @foreach ([
                'Date of birth' => $student->date_of_birth ? $student->date_of_birth->format('j M Y').' · '.$student->date_of_birth->age.' yrs' : '—',
                'Gender' => $student->gender ?? '—',
                'Religious background' => $student->religious_background ?? '—',
                'Admitted' => $student->admission_date?->format('j M Y') ?? '—',
            ] as $label => $value)
                <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">{{ $label }}</dt><dd class="{{ $dd }}">{{ $value }}</dd></div>
            @endforeach
        </dl>

        <p class="{{ $group }}">Guardians</p>
        <ul class="divide-y divide-neutral-100 text-sm">
            @forelse ($student->guardians as $guardian)
                <li class="flex justify-between items-start gap-4 px-4 py-2">
                    <div class="min-w-0">
                        <p class="text-ink font-medium truncate">{{ $guardian->user?->name ?? '—' }}</p>
                        <p class="text-xs text-neutral-500 truncate">{{ collect([$guardian->relationship, $guardian->user?->email, $guardian->phone])->filter()->implode(' · ') }}</p>
                    </div>
                    @if ($guardian->pivot->is_primary)
                        <x-badge color="teal" class="shrink-0 !px-2 !py-0.5 !text-[11px]">Primary</x-badge>
                    @endif
                </li>
            @empty
                <li class="px-4 py-2 text-neutral-400">No guardian linked yet.</li>
            @endforelse
        </ul>

        <p class="{{ $group }}">Enrollment history</p>
        <ul class="divide-y divide-neutral-100 text-sm">
            @forelse ($student->enrollments as $enrollment)
                <li class="flex justify-between items-center gap-4 px-4 py-2">
                    <span class="text-ink font-medium">
                        {{ $enrollment->section?->name ?? '—' }}
                        <span class="text-neutral-500 font-normal">· {{ $enrollment->section?->academicYear?->year_label }}</span>
                    </span>
                    <span class="text-xs font-medium {{ $enrollment->status === 'Active' ? 'text-success' : 'text-neutral-500' }}">{{ $enrollment->status }}</span>
                </li>
            @empty
                <li class="px-4 py-2 text-neutral-400">Not enrolled in a section yet.</li>
            @endforelse
        </ul>
    </div>

    {{-- Attendance --}}
    <div x-show="tab === 'attendance'" x-cloak role="tabpanel" class="max-h-[460px] overflow-y-auto">
        @if ($attendanceTotal)
            @php
                $bars = [
                    'Present' => 'bg-success',
                    'Tardy' => 'bg-gold',
                    'Excused' => 'bg-info',
                    'Absent' => 'bg-danger',
                ];
            @endphp
            <div class="px-4 py-4">
                <p class="text-xs text-neutral-500">Active academic year · {{ number_format($attendanceTotal) }} {{ Str::plural('day', $attendanceTotal) }} recorded</p>
                <div class="mt-2 flex h-2.5 rounded-full overflow-hidden bg-neutral-100" aria-hidden="true">
                    @foreach ($bars as $status => $color)
                        @if ($attendance[$status] ?? 0)
                            <span class="{{ $color }}" style="width: {{ ($attendance[$status] / $attendanceTotal) * 100 }}%"></span>
                        @endif
                    @endforeach
                </div>
            </div>
            <dl class="{{ $dl }} border-t border-neutral-100">
                @foreach ($bars as $status => $color)
                    <div class="flex justify-between items-center gap-4 px-4 py-2">
                        <dt class="flex items-center gap-2 {{ $dt }}"><span class="w-2.5 h-2.5 rounded-sm {{ $color }}" aria-hidden="true"></span>{{ $status }}</dt>
                        <dd class="{{ $dd }} tabular-nums">{{ $attendance[$status] ?? 0 }}</dd>
                    </div>
                @endforeach
                <div class="flex justify-between gap-4 px-4 py-2 bg-neutral-50">
                    <dt class="text-ink font-semibold">Attendance rate</dt>
                    <dd class="{{ $dd }} tabular-nums">{{ round($rate) }}%</dd>
                </div>
            </dl>
        @else
            <p class="px-4 py-4 text-sm text-neutral-400">No attendance recorded for the active academic year.</p>
        @endif
    </div>

    {{-- Documents --}}
    <div x-show="tab === 'documents'" x-cloak role="tabpanel" class="max-h-[460px] overflow-y-auto">
        <ul class="divide-y divide-neutral-100 text-sm">
            @forelse ($student->documentRequests as $doc)
                <li class="flex justify-between items-center gap-4 px-4 py-2">
                    <div class="min-w-0">
                        <p class="text-ink font-medium truncate">{{ $doc->type }}</p>
                        <p class="text-xs text-neutral-500">Requested {{ $doc->created_at?->format('j M Y') }}</p>
                    </div>
                    <x-badge color="yellow" class="shrink-0 !px-2 !py-0.5 !text-[11px]">{{ $doc->status }}</x-badge>
                </li>
            @empty
                <li class="px-4 py-4 text-neutral-400">No documents requested yet.</li>
            @endforelse
        </ul>
    </div>

    {{-- Footer --}}
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs">
        <a href="{{ route('registrar.students.show', $student) }}" class="font-semibold text-brand hover:underline">Open full profile →</a>
        @if ($student->enrollment_status === 'Enrolled')
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('registrar.students.transfer', $student) }}"
                      onsubmit="return confirm('Mark this student as Transferred and create a Transfer/Leaving Certificate draft?');">
                    @csrf
                    <button type="submit" class="font-semibold text-danger hover:underline">Transfer / Drop</button>
                </form>
                <form method="POST" action="{{ route('registrar.students.graduate', $student) }}"
                      onsubmit="return confirm('Mark this student as Graduated and create Completion Certificate + final transcript drafts?');">
                    @csrf
                    <button type="submit" class="font-semibold text-brand hover:underline">Mark Graduated</button>
                </form>
            </div>
        @endif
    </div>
</section>
