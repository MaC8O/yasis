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
                <button type="submit" class="flex-1 bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Filter</button>
                <a href="{{ route('admin.users.create') }}" class="flex-1 text-center bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Add User</a>
                <a href="{{ route('admin.users.import') }}" class="flex-1 text-center border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2.5 text-sm">Bulk Import</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Name</th>
                        <th class="py-2 font-semibold">ID / Email</th>
                        <th class="py-2 font-semibold">Role</th>
                        <th class="py-2 font-semibold">Status</th>
                        <th class="py-2 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">
                                <div class="flex items-center gap-2.5">
                                    @if ($user->photo_path)
                                        <img src="{{ Storage::url($user->photo_path) }}" alt=""
                                            class="w-8 h-8 rounded-full object-cover border border-neutral-200 shrink-0">
                                    @else
                                        <span class="w-8 h-8 rounded-full bg-[#C9A227] text-neutral-900 font-bold text-[10px] flex items-center justify-center shrink-0">
                                            {{ collect(explode(' ', $user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                                        </span>
                                    @endif
                                    {{ $user->name }}
                                </div>
                            </td>
                            <td class="py-2.5 text-neutral-500">{{ $user->staffProfile?->staff_id_number ?? $user->email }}</td>
                            <td class="py-2.5">{{ ucwords(str_replace('_', ' ', $user->roles->first()?->name ?? '—')) }}</td>
                            <td class="py-2.5">
                                <x-badge :color="$user->status === 'Active' ? 'green' : ($user->status === 'Pending' ? 'yellow' : 'pink')">
                                    {{ $user->status }}
                                </x-badge>
                                @if ($user->isLocked())
                                    <x-badge color="pink">Locked</x-badge>
                                @endif
                            </td>
                            <td class="py-2.5">
                                <div class="flex flex-wrap gap-3 text-xs font-semibold">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-[#1F573D] hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                                        @csrf
                                        <button type="submit" class="text-blue-700 hover:underline">Reset / Re-send</button>
                                    </form>
                                    @if ($user->isLocked())
                                        <form method="POST" action="{{ route('admin.users.unlock', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-yellow-700 hover:underline">Unlock</button>
                                        </form>
                                    @endif
                                    @if ($user->status === 'Active')
                                        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-red-700 hover:underline">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-green-700 hover:underline">Reactivate</button>
                                        </form>
                                    @endif
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                            onsubmit="return confirm('Delete {{ $user->name }}? If this account has linked records (grades, attendance, audit history, etc.) it will be anonymized and deactivated instead of removed, to preserve the audit trail.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-800 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-neutral-400">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>

        <p class="text-xs text-neutral-400 mt-4">Per-user actions: Edit · Reset password / Re-send login · Deactivate / Reactivate.</p>
    </x-card>

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
            <button type="submit" class="bg-[#B0392B] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Erase record</button>
        </form>
    </x-card>
</x-app-layout>
