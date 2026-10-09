<x-app-layout title="Staff Records" subtitle="All employees — teaching and non-teaching — with role, department, and status." badge="{{ $staff->count() }} staff" role="hr_office">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, ID, or role" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Department</label>
                <select name="department" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department'] ?? '') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-brand text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-brand-dark transition-colors">Filter</button>
                <a href="{{ route('hr_office.staff.create') }}" class="flex-1 text-center bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-700 transition-colors">+ Add staff</a>
            </div>
        </form>
    </x-card>

    <div x-data="masterDetail({{ $panel['staffMember']->id ?? 'null' }})"
         class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">
        {{-- Master list --}}
        <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-neutral-200 bg-neutral-50">
                <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-500">
                    <x-icon name="briefcase" class="w-4 h-4" /> Staff
                    <span class="rounded-full bg-neutral-200 text-neutral-700 px-2 py-0.5 text-[11px] tabular-nums">{{ $staff->count() }}</span>
                </p>
                <p class="text-[11px] text-neutral-400 hidden sm:block">Click to view · double-click to open full record</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 pl-4 pr-2 font-semibold">Staff</th>
                            <th class="py-2 px-2 font-semibold">Role</th>
                            <th class="py-2 px-2 font-semibold">Department</th>
                            <th class="py-2 px-2 font-semibold">Joined</th>
                            <th class="py-2 pl-2 pr-4 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($staff as $member)
                            <tr @click="select({{ $member->id }}, '{{ route('hr_office.staff.panel', $member) }}')"
                                @dblclick="open('{{ route('hr_office.staff.show', $member) }}')"
                                :class="selected === {{ $member->id }} ? 'bg-brand-soft shadow-[inset_3px_0_0_var(--color-brand)]' : 'hover:bg-neutral-50'"
                                class="border-b border-neutral-100 last:border-0 cursor-pointer select-none transition-colors">
                                <td class="py-2 pl-4 pr-2">
                                    <div class="flex items-center gap-2.5">
                                        @if ($member->user->photo_path)
                                            <img src="{{ Storage::url($member->user->photo_path) }}" alt="" class="w-8 h-8 rounded-full object-cover border border-neutral-200 shrink-0">
                                        @else
                                            <span class="w-8 h-8 rounded-full bg-gold text-neutral-900 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                {{ collect(explode(' ', $member->user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <button type="button" class="block text-left font-medium text-ink hover:underline"
                                                    :aria-pressed="selected === {{ $member->id }}">{{ $member->user->name }}</button>
                                            <span class="text-xs text-neutral-400 tabular-nums">{{ $member->staff_id_number }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 px-2">{{ $member->job_title }}</td>
                                <td class="py-2 px-2 text-neutral-500">{{ $member->department?->name ?? '—' }}</td>
                                <td class="py-2 px-2 text-neutral-500 whitespace-nowrap">{{ $member->joined_date?->format('d M Y') ?? '—' }}</td>
                                <td class="py-2 pl-2 pr-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $member->status === 'Active' ? 'text-success' : ($member->status === 'On Leave' ? 'text-info' : ($member->status === 'Probation' ? 'text-warning' : 'text-danger')) }}">
                                        <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>{{ $member->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 px-4 text-neutral-400">No staff records yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Detail panel --}}
        <div x-ref="panel" class="xl:sticky xl:top-6 scroll-mt-6 transition-opacity" :class="loading && 'opacity-50 pointer-events-none'" aria-live="polite">
            @if ($panel)
                @include('hr.staff.panel', $panel)
            @else
                <div class="bg-white rounded-2xl border border-dashed border-neutral-300 px-6 py-12 text-center text-sm text-neutral-400">
                    No staff member selected.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
