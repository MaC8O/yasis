<x-app-layout title="Edit User" :subtitle="$editUser->email" badge="Admin" role="admin">
    <x-card title="Profile photo">
        <div class="flex items-center gap-6">
            @if ($editUser->photo_path)
                <img src="{{ Storage::url($editUser->photo_path) }}" alt="{{ $editUser->name }}"
                    class="w-20 h-20 rounded-full object-cover border border-neutral-200">
            @else
                <div class="w-20 h-20 rounded-full bg-[#C9A227] text-neutral-900 font-bold text-xl flex items-center justify-center">
                    {{ collect(explode(' ', $editUser->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </div>
            @endif
            <div class="space-y-3">
                <form method="POST" action="{{ route('admin.users.photo.upload', $editUser) }}" enctype="multipart/form-data" class="flex gap-3 items-center">
                    @csrf
                    <input type="file" name="photo" required accept=".jpg,.jpeg,.png,.webp" class="text-sm">
                    <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Upload</button>
                </form>
                @if ($editUser->photo_path)
                    <form method="POST" action="{{ route('admin.users.photo.delete', $editUser) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm font-semibold text-red-700 hover:underline">Remove photo</button>
                    </form>
                @endif
                <p class="text-xs text-neutral-500">JPG, PNG, or WebP · max 2 MB.</p>
            </div>
        </div>
    </x-card>

    <x-card>
        <form method="POST" action="{{ route('admin.users.update', $editUser) }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Full name</label>
                    <input type="text" name="name" value="{{ old('name', $editUser->name) }}" required
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $editUser->email) }}" required
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $editUser->date_of_birth?->toDateString()) }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Gender</label>
                    <select name="gender" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">—</option>
                        <option value="Male" @selected(old('gender', $editUser->gender) === 'Male')>Male</option>
                        <option value="Female" @selected(old('gender', $editUser->gender) === 'Female')>Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $editUser->phone) }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Address</label>
                    <input type="text" name="address" value="{{ old('address', $editUser->address) }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Role</label>
                <select name="role" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($roles as $r)
                        <option value="{{ $r }}" @selected(old('role', $editUser->roles->first()?->name) === $r)>{{ ucwords(str_replace('_', ' ', $r)) }}</option>
                    @endforeach
                </select>
            </div>

            @if ($editUser->staffProfile)
                <div class="border-t border-neutral-100 pt-4 text-sm text-neutral-500">
                    Staff ID: <span class="font-semibold text-neutral-800">{{ $editUser->staffProfile->staff_id_number }}</span>
                    · Employment status: <span class="font-semibold text-neutral-800">{{ $editUser->staffProfile->status }}</span>
                </div>
            @endif

            <div class="flex gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Save changes</button>
                <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
            </div>
        </form>
    </x-card>

    <x-card title="Set a new password" subtitle="Directly sets the login password — use this for in-person handoff. The self-service reset-link email is still available from the user list.">
        <form method="POST" action="{{ route('admin.users.set-password', $editUser) }}" class="space-y-4" x-data="{ password: '' }">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">New password</label>
                    <input type="text" name="password" x-model="password" required minlength="8"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Confirm password</label>
                    <input type="text" name="password_confirmation" required minlength="8"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm font-mono">
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-neutral-900 text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Set password</button>
                <button type="button"
                    @click="password = Array.from(crypto.getRandomValues(new Uint8Array(12))).map(b => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%'[b % 60]).join(''); $el.closest('form').querySelector('[name=password_confirmation]').value = password"
                    class="text-sm font-semibold text-neutral-600 hover:underline self-center">Generate strong password</button>
            </div>
            <p class="text-xs text-neutral-500">Minimum 8 characters. The account's failed-attempt counter and any lock are cleared when a new password is set.</p>
        </form>
    </x-card>
</x-app-layout>
