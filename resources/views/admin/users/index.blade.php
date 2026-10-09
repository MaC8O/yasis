<x-app-layout title="User Management" subtitle="Create, edit, deactivate/reactivate accounts and assign roles." badge="Admin" role="admin">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-6 gap-4 items-end">
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold mb-1">Search users</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name or email"
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Role filter</label>
                <select name="role" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Show</label>
                <select name="per_page" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach (\App\Support\PerPage::OPTIONS as $value)
                        <option value="{{ $value }}" @selected((string) request('per_page', '15') === (string) $value)>{{ $value }}</option>
                    @endforeach
                    <option value="all" @selected(request('per_page') === 'all')>All</option>
                </select>
            </div>
            <div class="sm:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 bg-brand text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-brand-dark transition-colors">Filter</button>
                <a href="{{ route('admin.users.create') }}" class="flex-1 text-center bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-700 transition-colors">Add User</a>
                <a href="{{ route('admin.users.import') }}" class="flex-1 text-center border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-50 transition-colors">Bulk Import</a>
            </div>
        </form>
    </x-card>

    <div x-data="masterDetail({{ $panel['profileUser']->id ?? 'null' }})"
         class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">
        {{-- Master list --}}
        <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-neutral-200 bg-neutral-50">
                <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-500">
                    <x-icon name="users" class="w-4 h-4" /> Users
                    <span class="rounded-full bg-neutral-200 text-neutral-700 px-2 py-0.5 text-[11px] tabular-nums">{{ $users->total() }}</span>
                </p>
                <p class="text-[11px] text-neutral-400 hidden sm:block">Click to view · double-click to open full profile</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 pl-4 pr-2 font-semibold">Name</th>
                            <th class="py-2 px-2 font-semibold">ID / Email</th>
                            <th class="py-2 px-2 font-semibold">Role</th>
                            <th class="py-2 pl-2 pr-4 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr @click="select({{ $user->id }}, '{{ route('admin.users.panel', $user) }}')"
                                @dblclick="open('{{ route('admin.users.edit', $user) }}')"
                                :class="selected === {{ $user->id }} ? 'bg-brand-soft shadow-[inset_3px_0_0_var(--color-brand)]' : 'hover:bg-neutral-50'"
                                class="border-b border-neutral-100 last:border-0 cursor-pointer select-none transition-colors">
                                <td class="py-2 pl-4 pr-2">
                                    <div class="flex items-center gap-2.5">
                                        @if ($user->photo_path)
                                            <img src="{{ Storage::url($user->photo_path) }}" alt=""
                                                class="w-8 h-8 rounded-full object-cover border border-neutral-200 shrink-0">
                                        @else
                                            <span class="w-8 h-8 rounded-full bg-gold text-neutral-900 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                {{ collect(explode(' ', $user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                                            </span>
                                        @endif
                                        <button type="button" class="text-left font-medium text-ink hover:underline"
                                                :aria-pressed="selected === {{ $user->id }}">{{ $user->name }}</button>
                                    </div>
                                </td>
                                <td class="py-2 px-2 text-neutral-500">{{ $user->staffProfile?->staff_id_number ?? $user->email }}</td>
                                <td class="py-2 px-2">{{ ucwords(str_replace('_', ' ', $user->roles->first()?->name ?? '—')) }}</td>
                                <td class="py-2 pl-2 pr-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $user->status === 'Active' ? 'text-success' : ($user->status === 'Pending' ? 'text-warning' : 'text-danger') }}">
                                        <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>{{ $user->status }}
                                    </span>
                                    @if ($user->isLocked())
                                        <span class="ml-1 text-xs font-semibold text-danger">· Locked</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 px-4 text-neutral-400">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-neutral-200 bg-neutral-50 text-xs">{{ $users->links() }}</div>
        </div>

        {{-- Detail panel --}}
        <div x-ref="panel" class="xl:sticky xl:top-6 scroll-mt-6 transition-opacity" :class="loading && 'opacity-50 pointer-events-none'" aria-live="polite">
            @if ($panel)
                @include('admin.users.panel', $panel)
            @else
                <div class="bg-white rounded-2xl border border-dashed border-neutral-300 px-6 py-12 text-center text-sm text-neutral-400">
                    No user selected.
                </div>
            @endif
        </div>
    </div>


    <x-card title="Data Retention" subtitle="Action an erasure/retention request against a named student or guardian. PII is scrubbed and portal access revoked — history is retained anonymized, never hard-deleted. Every action is audited.">
        <form method="POST" action="{{ route('admin.retention-actions.store') }}" x-data="{ type: '{{ old('subject_type', 'student') }}' }"
              onsubmit="return confirm('Erase this record? PII will be scrubbed and portal access revoked. This cannot be undone.');"
              class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Subject</label>
                <select name="subject_type" x-model="type" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="student">Student</option>
                    <option value="guardian">Guardian</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" x-text="type === 'student' ? 'Student ID' : 'Guardian email'"></label>
                <input type="text" name="identifier" value="{{ old('identifier') }}" required
                       x-bind:placeholder="type === 'student' ? 'YAS-2026-0001' : 'guardian@example.com'"
                       class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Reason (required, audited)</label>
                <input type="text" name="reason" value="{{ old('reason') }}" required maxlength="200"
                       placeholder="e.g. Family erasure request, retention period lapsed"
                       class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-danger text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-danger-dark transition-colors">Erase record</button>
        </form>
    </x-card>
</x-app-layout>
