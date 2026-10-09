{{--
    Staff detail panel (master-detail on Staff Records). Rendered inline on first load and
    re-fetched from hr_office.staff.panel whenever another row is selected.
--}}
@php
    $u = $staffMember->user;
    $initials = collect(explode(' ', $u->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $statusColor = $staffMember->status === 'Active' ? 'green' : ($staffMember->status === 'On Leave' ? 'blue' : ($staffMember->status === 'Probation' ? 'yellow' : 'pink'));
    // Personnel-only records get a synthesized address that can never receive mail.
    $email = str_ends_with($u->email, '@internal.yasis.edu') ? null : $u->email;
    $phone = $staffMember->phone ?? $u->phone;
    $hasPortal = $staffMember->role_type !== 'Staff';
    $isTeacher = $staffMember->teachingAssignments->isNotEmpty() || $staffMember->homeroomSections->isNotEmpty();
    $attendanceTotal = $attendance->sum();

    $circle = 'w-9 h-9 rounded-full border border-white/40 flex items-center justify-center transition-colors group-hover:bg-white group-hover:text-brand';
    $tile = 'group flex flex-col items-center gap-1 w-14 text-white text-[11px] font-medium';
    $off = 'flex flex-col items-center gap-1 w-14 text-white/35 text-[11px] font-medium cursor-not-allowed';
    $dl = 'divide-y divide-neutral-100 text-sm';
    $dt = 'text-neutral-500 shrink-0';
    $dd = 'text-right text-ink font-medium min-w-0 break-words';
    $group = 'px-4 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-neutral-400 bg-neutral-50 border-y border-neutral-100';
@endphp

<section aria-label="Staff information" x-data="{ tab: 'profile' }"
         class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">

    {{-- Panel title bar --}}
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-neutral-200 bg-neutral-50">
        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-500">
            <x-icon name="info" class="w-4 h-4" /> Information
        </p>
        <span class="text-[11px] text-neutral-400 tabular-nums">{{ $staffMember->staff_id_number }}</span>
    </div>

    {{-- Command band: job title block + quick actions --}}
    <div class="grid grid-cols-[80px_1fr]">
        <div class="bg-gold text-ink flex flex-col items-center justify-center text-center px-2 py-4">
            <x-icon :name="$isTeacher ? 'book' : 'briefcase'" class="w-5 h-5 opacity-70" />
            <p class="mt-1.5 text-sm font-bold leading-tight break-words">{{ $staffMember->job_title ?? 'Staff' }}</p>
        </div>
        <div class="bg-brand px-1 py-3 flex flex-wrap items-start justify-around gap-y-2">
            <a href="{{ route('hr_office.staff.show', $staffMember) }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="eye" class="w-4 h-4" /></span> Record
            </a>

            @if ($email)
                <a href="mailto:{{ $email }}" class="{{ $tile }}">
                    <span class="{{ $circle }}"><x-icon name="mail" class="w-4 h-4" /></span> Email
                </a>
            @else
                <span class="{{ $off }}" title="No mailbox — personnel record only">
                    <span class="w-9 h-9 rounded-full border border-white/20 flex items-center justify-center"><x-icon name="mail" class="w-4 h-4" /></span> Email
                </span>
            @endif

            @if ($phone)
                <a href="tel:{{ $phone }}" class="{{ $tile }}">
                    <span class="{{ $circle }}"><x-icon name="phone" class="w-4 h-4" /></span> Call
                </a>
            @else
                <span class="{{ $off }}" title="No phone number on file">
                    <span class="w-9 h-9 rounded-full border border-white/20 flex items-center justify-center"><x-icon name="phone" class="w-4 h-4" /></span> Call
                </span>
            @endif

            <a href="{{ route('hr_office.leave.index') }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="leave" class="w-4 h-4" /></span> Leave
            </a>

            <a href="{{ route('hr_office.attendance.index') }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="clock" class="w-4 h-4" /></span> Attendance
            </a>
        </div>
    </div>

    {{-- Status strip --}}
    <div class="flex items-center justify-between gap-3 bg-tint-yellow text-tint-yellow-ink text-xs px-4 py-1.5">
        <span class="flex items-center gap-1.5">
            <x-icon name="calendar" class="w-3.5 h-3.5" />
            @if ($onLeave)
                On {{ $onLeave->leaveType?->name ?? '' }} leave until <strong>{{ $onLeave->to_date->format('j M') }}</strong>
            @elseif ($staffMember->joined_date)
                Joined <strong>{{ $staffMember->joined_date->format('j M Y') }}</strong> · {{ $staffMember->joined_date->diffForHumans(null, true) }}
            @else
                No join date on file
            @endif
        </span>
        @if ($pendingLeave)
            <span class="font-semibold">{{ $pendingLeave }} leave {{ Str::plural('request', $pendingLeave) }} pending</span>
        @endif
    </div>

    {{-- Identity --}}
    <div class="flex items-center gap-4 px-4 py-4 border-b border-neutral-200">
        @if ($u->photo_path)
            <img src="{{ Storage::url($u->photo_path) }}" alt="" class="w-20 h-20 rounded-full object-cover border border-neutral-200 shrink-0">
        @else
            <span class="w-20 h-20 rounded-full bg-neutral-100 border border-neutral-200 text-neutral-500 font-bold text-2xl flex items-center justify-center shrink-0">{{ $initials }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-lg font-bold text-ink leading-tight truncate">{{ $u->name }}</p>
            <p class="mt-1 text-base font-bold text-ink tabular-nums">{{ $staffMember->staff_id_number }}</p>
            <p class="text-sm text-neutral-600 truncate">{{ $staffMember->department?->name ?? 'No department' }}</p>
        </div>
        <x-badge :color="$statusColor" class="shrink-0 self-start">{{ $staffMember->status }}</x-badge>
    </div>

    {{-- Tabs --}}
    <div role="tablist" aria-label="Staff details" class="flex border-b border-neutral-200 px-2 text-xs font-bold uppercase tracking-wide">
        @foreach (['profile' => 'Profile', 'leave' => 'Leave', 'attendance' => 'Attendance'] as $key => $label)
            <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'text-brand border-brand' : 'text-neutral-400 border-transparent hover:text-neutral-700'"
                    class="px-3 py-2.5 border-b-2 -mb-px transition-colors">
                {{ $label }}
                @if ($key === 'leave' && $pendingLeave)
                    <span class="ml-1 rounded-full bg-tint-yellow text-tint-yellow-ink px-1.5 py-0.5 text-[10px] tabular-nums">{{ $pendingLeave }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Profile --}}
    <div x-show="tab === 'profile'" role="tabpanel" class="max-h-[460px] overflow-y-auto">
        <p class="{{ $group }} border-t-0">Employment</p>
        <dl class="{{ $dl }}">
            @foreach ([
                'Job title' => $staffMember->job_title ?? '—',
                'Department' => $staffMember->department?->name ?? '—',
                'Joined' => $staffMember->joined_date?->format('j M Y') ?? '—',
                'Portal access' => $hasPortal ? 'Yes — '.ucwords(str_replace('_', ' ', $staffMember->role_type)) : 'No (personnel record only)',
            ] as $label => $value)
                <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">{{ $label }}</dt><dd class="{{ $dd }}">{{ $value }}</dd></div>
            @endforeach
        </dl>

        <p class="{{ $group }}">Contact</p>
        <dl class="{{ $dl }}">
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Email</dt><dd class="{{ $dd }}">{{ $email ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Phone</dt><dd class="{{ $dd }}">{{ $phone ?? '—' }}</dd></div>
        </dl>

        @if ($isTeacher)
            <p class="{{ $group }}">Teaching this year</p>
            <dl class="{{ $dl }}">
                <div class="flex justify-between gap-4 px-4 py-2">
                    <dt class="{{ $dt }}">Homeroom</dt>
                    <dd class="{{ $dd }}">{{ $staffMember->homeroomSections->pluck('name')->implode(', ') ?: '—' }}</dd>
                </div>
            </dl>
            <ul class="divide-y divide-neutral-100 text-sm border-t border-neutral-100">
                @forelse ($staffMember->teachingAssignments->sortBy(fn ($a) => [$a->section?->name, $a->subject?->name]) as $assignment)
                    <li class="flex justify-between gap-4 px-4 py-2">
                        <span class="text-ink font-medium">{{ $assignment->subject?->name ?? '—' }}</span>
                        <span class="text-neutral-500">{{ $assignment->section?->name }}</span>
                    </li>
                @empty
                    <li class="px-4 py-2 text-neutral-400">No classes assigned.</li>
                @endforelse
            </ul>
        @endif
    </div>

    {{-- Leave --}}
    <div x-show="tab === 'leave'" x-cloak role="tabpanel" class="max-h-[460px] overflow-y-auto">
        <p class="{{ $group }} border-t-0">Balances · {{ now()->year }}</p>
        <ul class="divide-y divide-neutral-100 text-sm">
            @forelse ($staffMember->leaveBalances as $balance)
                @php
                    $remaining = $balance->allocated - $balance->used - $balance->pending;
                    $usedPct = $balance->allocated ? min(100, ($balance->used + $balance->pending) / $balance->allocated * 100) : 0;
                @endphp
                <li class="px-4 py-2.5">
                    <div class="flex justify-between gap-4">
                        <span class="text-ink font-medium">{{ $balance->leaveType?->name }}</span>
                        @if ($balance->allocated)
                            <span class="tabular-nums {{ $remaining <= 0 ? 'text-danger font-semibold' : 'text-neutral-600' }}">{{ $remaining }} of {{ $balance->allocated }} left</span>
                        @else
                            {{-- e.g. Unpaid leave: no allowance to run out of, only days taken. --}}
                            <span class="tabular-nums text-neutral-500">{{ $balance->used ? $balance->used.' '.Str::plural('day', $balance->used).' taken' : 'No allowance' }}</span>
                        @endif
                    </div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-neutral-100 overflow-hidden" aria-hidden="true">
                        <span class="block h-full bg-brand" @style(['width: '.$usedPct.'%'])></span>
                    </div>
                    @if ($balance->pending)
                        <p class="mt-1 text-xs text-warning">{{ $balance->pending }} {{ Str::plural('day', $balance->pending) }} pending approval</p>
                    @endif
                </li>
            @empty
                <li class="px-4 py-3 text-neutral-400">No leave balances set up for {{ now()->year }}.</li>
            @endforelse
        </ul>

        <p class="{{ $group }}">Recent requests</p>
        <ul class="divide-y divide-neutral-100 text-sm">
            @forelse ($staffMember->leaveRequests as $leave)
                <li class="flex justify-between items-center gap-4 px-4 py-2">
                    <div class="min-w-0">
                        <p class="text-ink font-medium">{{ $leave->leaveType?->name }} · {{ $leave->days }} {{ Str::plural('day', $leave->days) }}</p>
                        <p class="text-xs text-neutral-500">{{ $leave->from_date?->format('j M') }} – {{ $leave->to_date?->format('j M Y') }}</p>
                    </div>
                    <x-badge :color="$leave->status === 'Approved' ? 'green' : ($leave->status === 'Pending' ? 'yellow' : 'pink')" class="shrink-0 !px-2 !py-0.5 !text-[11px]">{{ $leave->status }}</x-badge>
                </li>
            @empty
                <li class="px-4 py-3 text-neutral-400">No leave requests yet.</li>
            @endforelse
        </ul>
    </div>

    {{-- Attendance --}}
    <div x-show="tab === 'attendance'" x-cloak role="tabpanel" class="max-h-[460px] overflow-y-auto">
        @php
            $bars = ['Present' => 'bg-success', 'Tardy' => 'bg-gold', 'On-Leave' => 'bg-info', 'Absent' => 'bg-danger'];
        @endphp
        @if ($attendanceTotal)
            <div class="px-4 py-4">
                <p class="text-xs text-neutral-500">{{ now()->format('F Y') }} · {{ $attendanceTotal }} {{ Str::plural('day', $attendanceTotal) }} recorded</p>
                <div class="mt-2 flex h-2.5 rounded-full overflow-hidden bg-neutral-100" aria-hidden="true">
                    @foreach ($bars as $status => $color)
                        @if ($attendance[$status] ?? 0)
                            <span class="{{ $color }}" @style(['width: '.$attendance[$status] / $attendanceTotal * 100 .'%'])></span>
                        @endif
                    @endforeach
                </div>
            </div>
            <dl class="{{ $dl }} border-t border-neutral-100">
                @foreach ($bars as $status => $color)
                    <div class="flex justify-between items-center gap-4 px-4 py-2">
                        <dt class="flex items-center gap-2 {{ $dt }}"><span class="w-2.5 h-2.5 rounded-sm {{ $color }}" aria-hidden="true"></span>{{ $status === 'On-Leave' ? 'On leave' : $status }}</dt>
                        <dd class="{{ $dd }} tabular-nums">{{ $attendance[$status] ?? 0 }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="px-4 py-4 text-sm text-neutral-400">No attendance recorded for {{ now()->format('F Y') }}.</p>
        @endif
    </div>

    {{-- Footer: quick employment-status change (same action as the full record) --}}
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs">
        <a href="{{ route('hr_office.staff.show', $staffMember) }}" class="font-semibold text-brand hover:underline shrink-0">Open full record →</a>
        <form method="POST" action="{{ route('hr_office.staff.status', $staffMember) }}" class="flex items-center gap-1.5">
            @csrf @method('PUT')
            <label for="panel-status-{{ $staffMember->id }}" class="sr-only">Employment status</label>
            <select id="panel-status-{{ $staffMember->id }}" name="status" class="rounded-md border border-neutral-200 bg-white px-2 py-1 text-xs">
                @foreach (['Active', 'On Leave', 'Probation', 'Inactive'] as $status)
                    <option value="{{ $status }}" @selected($staffMember->status === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button type="submit" class="font-semibold text-brand hover:underline">Update</button>
        </form>
    </div>
</section>
