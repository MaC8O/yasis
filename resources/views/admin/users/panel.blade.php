{{--
    User profile detail panel (master-detail on User Management). Rendered inline on first
    load and re-fetched from admin.users.panel whenever another row is selected.
--}}
@php
    $u = $profileUser;
    $role = $u->roles->first()?->name;
    $roleLabel = $role ? ucwords(str_replace('_', ' ', $role)) : 'No role';
    $staff = $u->staffProfile;
    $initials = collect(explode(' ', $u->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $identifier = $staff?->staff_id_number ?? $u->student?->student_id_number;
    $department = $staff?->department?->name ?? $u->student?->department?->name;
    $statusColor = $u->status === 'Active' ? 'green' : ($u->status === 'Pending' ? 'yellow' : 'pink');
    $isSelf = $u->id === auth()->id();

    // Drops rows whose value is false (fields that don't apply to this user).
    $rows = fn (array $pairs) => collect($pairs)->filter(fn ($v) => $v !== false);
@endphp

<section aria-label="User information" x-data="{ tab: 'profile' }"
         class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">

    {{-- Panel title bar --}}
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-neutral-200 bg-neutral-50">
        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-500">
            <x-icon name="info" class="w-4 h-4" /> Information
        </p>
        <span class="text-[11px] text-neutral-400 tabular-nums">User #{{ $u->id }}</span>
    </div>

    {{-- Command band: role block + quick actions --}}
    <div class="grid grid-cols-[80px_1fr]">
        <div class="bg-gold text-ink flex flex-col items-center justify-center text-center px-2 py-4">
            <x-icon :name="$staff ? 'briefcase' : ($u->student ? 'student' : 'users')" class="w-5 h-5 opacity-70" />
            <p class="mt-1.5 text-sm font-bold leading-tight">{{ $roleLabel }}</p>
        </div>
        <div class="bg-brand px-1 py-3 flex flex-wrap items-start justify-around gap-y-2">
            @php
                $circle = 'w-9 h-9 rounded-full border border-white/40 flex items-center justify-center transition-colors group-hover:bg-white group-hover:text-brand';
                $tile = 'group flex flex-col items-center gap-1 w-14 text-white text-[11px] font-medium';
                $off = 'flex flex-col items-center gap-1 w-14 text-white/35 text-[11px] font-medium cursor-not-allowed';
            @endphp

            <a href="{{ route('admin.users.edit', $u) }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="pencil" class="w-4 h-4" /></span> Edit
            </a>

            <a href="mailto:{{ $u->email }}" class="{{ $tile }}">
                <span class="{{ $circle }}"><x-icon name="mail" class="w-4 h-4" /></span> Email
            </a>

            @if ($u->phone || $staff?->phone)
                <a href="tel:{{ $u->phone ?? $staff->phone }}" class="{{ $tile }}">
                    <span class="{{ $circle }}"><x-icon name="phone" class="w-4 h-4" /></span> Call
                </a>
            @else
                <span class="{{ $off }}" title="No phone number on file">
                    <span class="w-9 h-9 rounded-full border border-white/20 flex items-center justify-center"><x-icon name="phone" class="w-4 h-4" /></span> Call
                </span>
            @endif

            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}">
                @csrf
                <button type="submit" class="{{ $tile }}" title="Force a password reset and e-mail a fresh link">
                    <span class="{{ $circle }}"><x-icon name="key" class="w-4 h-4" /></span> Reset
                </button>
            </form>

            @if ($u->isLocked())
                <form method="POST" action="{{ route('admin.users.unlock', $u) }}">
                    @csrf
                    <button type="submit" class="{{ $tile }}">
                        <span class="{{ $circle }} bg-warning border-warning"><x-icon name="lock-open" class="w-4 h-4" /></span> Unlock
                    </button>
                </form>
            @endif

            @if ($u->status === 'Active')
                <form method="POST" action="{{ route('admin.users.deactivate', $u) }}"
                      onsubmit="return confirm('Deactivate {{ e(addslashes($u->name)) }}? They will no longer be able to sign in.');">
                    @csrf
                    <button type="submit" class="{{ $tile }}">
                        <span class="{{ $circle }}"><x-icon name="power" class="w-4 h-4" /></span> Deactivate
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.reactivate', $u) }}">
                    @csrf
                    <button type="submit" class="{{ $tile }}">
                        <span class="{{ $circle }}"><x-icon name="power" class="w-4 h-4" /></span> Reactivate
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Status strip --}}
    <div class="flex items-center justify-between gap-3 bg-tint-yellow text-tint-yellow-ink text-xs px-4 py-1.5">
        <span class="flex items-center gap-1.5">
            <x-icon name="clock" class="w-3.5 h-3.5" />
            @if ($u->last_login_at)
                Last sign-in <strong title="{{ $u->last_login_at->format('j M Y, H:i') }}">{{ $u->last_login_at->diffForHumans() }}</strong>
            @else
                Never signed in
            @endif
        </span>
        @if ($u->isLocked())
            <span class="font-semibold text-danger">Locked</span>
        @elseif ($u->must_reset_password)
            <span class="font-semibold">Password reset pending</span>
        @endif
    </div>

    {{-- Identity --}}
    <div class="flex items-center gap-4 px-4 py-4 border-b border-neutral-200">
        <a href="{{ route('admin.users.edit', $u) }}" class="group relative shrink-0 rounded-full" title="Change photo">
            @if ($u->photo_path)
                <img src="{{ Storage::url($u->photo_path) }}" alt="" class="w-20 h-20 rounded-full object-cover border border-neutral-200">
            @else
                <span class="w-20 h-20 rounded-full bg-neutral-100 border border-neutral-200 text-neutral-500 font-bold text-2xl flex items-center justify-center">{{ $initials }}</span>
            @endif
            <span class="absolute inset-0 rounded-full bg-ink/55 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition-opacity">
                <x-icon name="camera" class="w-5 h-5" />
            </span>
        </a>
        <div class="min-w-0 flex-1">
            <p class="text-lg font-bold text-ink leading-tight truncate">{{ $u->name }}</p>
            <p class="text-sm text-neutral-500 truncate">{{ $u->email }}</p>
            @if ($identifier)
                <p class="mt-1 text-base font-bold text-ink tabular-nums">{{ $identifier }}</p>
            @endif
            @if ($department)
                <p class="text-sm text-neutral-600 truncate">{{ $department }}</p>
            @endif
        </div>
        <x-badge :color="$statusColor" class="shrink-0 self-start">{{ $u->status }}</x-badge>
    </div>

    {{-- Tabs --}}
    <div role="tablist" aria-label="User details" class="flex border-b border-neutral-200 px-2 text-xs font-bold uppercase tracking-wide">
        @foreach (['profile' => 'Profile', 'security' => 'Security', 'activity' => 'Activity'] as $key => $label)
            <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'text-brand border-brand' : 'text-neutral-400 border-transparent hover:text-neutral-700'"
                    class="px-3 py-2.5 border-b-2 -mb-px transition-colors">
                {{ $label }}
                @if ($key === 'activity' && $actionsByCount)
                    <span class="ml-1 rounded-full bg-neutral-100 text-neutral-600 px-1.5 py-0.5 text-[10px] tabular-nums">{{ $actionsByCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @php
        $dl = 'divide-y divide-neutral-100 text-sm';
        $dt = 'text-neutral-500 shrink-0';
        $dd = 'text-right text-ink font-medium min-w-0 break-words';
        $group = 'px-4 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-neutral-400 bg-neutral-50 border-y border-neutral-100';
    @endphp

    {{-- Profile --}}
    <div x-show="tab === 'profile'" role="tabpanel" class="max-h-[460px] overflow-y-auto">
        <p class="{{ $group }} border-t-0">Personal</p>
        <dl class="{{ $dl }}">
            @foreach ($rows([
                'Phone' => $u->phone ?? $staff?->phone ?? '—',
                'Gender' => $u->gender ?? '—',
                'Date of birth' => $u->date_of_birth ? $u->date_of_birth->format('j M Y').' · '.$u->date_of_birth->age.' yrs' : '—',
                'Address' => $u->address ?? '—',
            ]) as $label => $value)
                <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">{{ $label }}</dt><dd class="{{ $dd }}">{{ $value }}</dd></div>
            @endforeach
        </dl>

        @if ($staff)
            <p class="{{ $group }}">Employment</p>
            <dl class="{{ $dl }}">
                @foreach ($rows([
                    'Staff ID' => $staff->staff_id_number,
                    'Job title' => $staff->job_title ?? $staff->role_type ?? '—',
                    'Department' => $staff->department?->name ?? '—',
                    'Joined' => $staff->joined_date ? $staff->joined_date->format('j M Y').' · '.$staff->joined_date->diffForHumans(null, true) : '—',
                    'Employment status' => $staff->status ?? '—',
                    'Homeroom' => $staff->homeroomSections->isNotEmpty() ? $staff->homeroomSections->pluck('name')->implode(', ') : false,
                    'Teaching load' => $staff->teaching_assignments_count ? Str::plural('class', $staff->teaching_assignments_count, true).' assigned' : false,
                ]) as $label => $value)
                    <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">{{ $label }}</dt><dd class="{{ $dd }}">{{ $value }}</dd></div>
                @endforeach
            </dl>
        @endif

        @if ($u->student)
            <p class="{{ $group }}">Student record</p>
            <dl class="{{ $dl }}">
                @foreach ([
                    'Student ID' => $u->student->student_id_number,
                    'Department' => $u->student->department?->name ?? '—',
                    'Admitted' => $u->student->admission_date?->format('j M Y') ?? '—',
                    'Enrollment' => $u->student->enrollment_status ?? '—',
                ] as $label => $value)
                    <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">{{ $label }}</dt><dd class="{{ $dd }}">{{ $value }}</dd></div>
                @endforeach
            </dl>
        @endif

        @if ($u->guardian)
            <p class="{{ $group }}">Guardian of</p>
            <ul class="divide-y divide-neutral-100 text-sm">
                @forelse ($u->guardian->students as $child)
                    <li class="flex justify-between gap-4 px-4 py-2">
                        <span class="text-ink font-medium">{{ $child->name }}</span>
                        <span class="text-neutral-500 tabular-nums">
                            {{ $child->student_id_number }}
                            @if ($child->pivot->is_primary) <x-badge color="teal" class="ml-1 !px-2 !py-0.5 !text-[11px]">Primary</x-badge> @endif
                        </span>
                    </li>
                @empty
                    <li class="px-4 py-2 text-neutral-400">No students linked.</li>
                @endforelse
            </ul>
        @endif

        <p class="{{ $group }}">Account</p>
        <dl class="{{ $dl }}">
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Created</dt><dd class="{{ $dd }}">{{ $u->created_at?->format('j M Y') ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Last updated</dt><dd class="{{ $dd }}">{{ $u->updated_at?->diffForHumans() ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Security --}}
    <div x-show="tab === 'security'" x-cloak role="tabpanel" class="max-h-[460px] overflow-y-auto">
        <dl class="{{ $dl }}">
            <div class="flex justify-between items-center gap-4 px-4 py-2"><dt class="{{ $dt }}">Account status</dt><dd><x-badge :color="$statusColor" class="!px-2.5 !py-0.5 !text-xs">{{ $u->status }}</x-badge></dd></div>
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Password</dt><dd class="{{ $dd }}">{{ $u->status === 'Pending' ? 'Not yet set (setup link sent)' : ($u->must_reset_password ? 'Must reset on next sign-in' : 'Set') }}</dd></div>
            <div class="flex justify-between gap-4 px-4 py-2">
                <dt class="{{ $dt }}">Failed sign-ins</dt>
                <dd class="{{ $dd }} tabular-nums {{ $u->failed_login_attempts ? 'text-warning' : '' }}">{{ (int) $u->failed_login_attempts }} / {{ $lockoutThreshold }}</dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Locked until</dt><dd class="{{ $dd }} {{ $u->isLocked() ? 'text-danger' : '' }}">{{ $u->isLocked() ? $u->locked_until->format('j M Y, H:i') : 'Not locked' }}</dd></div>
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Last sign-in</dt><dd class="{{ $dd }}">{{ $u->last_login_at?->format('j M Y, H:i') ?? 'Never' }}</dd></div>
            <div class="flex justify-between gap-4 px-4 py-2"><dt class="{{ $dt }}">Email verified</dt><dd class="{{ $dd }}">{{ $u->email_verified_at?->format('j M Y') ?? 'No' }}</dd></div>
        </dl>

        <p class="{{ $group }}">Changes to this account</p>
        <ol class="px-4 py-3 space-y-3 text-sm">
            @forelse ($accountHistory as $log)
                <li class="flex gap-3">
                    <span class="mt-1.5 w-2 h-2 rounded-full bg-gold shrink-0" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="text-ink">{{ $log->action }}</p>
                        <p class="text-xs text-neutral-500">by {{ $log->user?->name ?? 'System' }} · {{ $log->created_at?->diffForHumans() }}</p>
                    </div>
                </li>
            @empty
                <li class="text-neutral-400">No administrative changes recorded.</li>
            @endforelse
        </ol>
    </div>

    {{-- Activity --}}
    <div x-show="tab === 'activity'" x-cloak role="tabpanel" class="max-h-[460px] overflow-y-auto">
        <ol class="px-4 py-3 space-y-3 text-sm">
            @forelse ($actionsBy as $log)
                <li class="flex gap-3">
                    <span class="mt-1.5 w-2 h-2 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-ink">{{ $log->action }}</p>
                        <p class="text-xs text-neutral-500">
                            {{ $log->category }} · <span title="{{ $log->created_at?->format('j M Y, H:i') }}">{{ $log->created_at?->diffForHumans() }}</span>
                        </p>
                    </div>
                </li>
            @empty
                <li class="text-neutral-400">This user hasn't performed any recorded actions yet.</li>
            @endforelse
        </ol>
        @if ($actionsByCount > $actionsBy->count())
            <p class="px-4 pb-3 text-xs text-neutral-500">
                Showing the latest {{ $actionsBy->count() }} of {{ number_format($actionsByCount) }} —
                <a href="{{ route('admin.audit-logs.index', ['search' => $u->name]) }}" class="font-semibold text-brand hover:underline">open in Audit Logs</a>
            </p>
        @endif
    </div>

    {{-- Footer --}}
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-t border-neutral-200 bg-neutral-50 text-xs">
        <a href="{{ route('admin.users.edit', $u) }}" class="font-semibold text-brand hover:underline">Open full profile →</a>
        @unless ($isSelf)
            <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                  onsubmit="return confirm('Delete {{ e(addslashes($u->name)) }}? If this account has linked records (grades, attendance, audit history, etc.) it will be anonymized and deactivated instead of removed, to preserve the audit trail.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="flex items-center gap-1 font-semibold text-danger hover:underline">
                    <x-icon name="trash" class="w-3.5 h-3.5" /> Delete account
                </button>
            </form>
        @endunless
    </div>
</section>
