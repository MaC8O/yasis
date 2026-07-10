<x-app-layout title="Add User" subtitle="Create a login account and assign a role." badge="Admin" role="admin">
    <x-card>
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5" x-data="{ role: 'admin' }">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Full name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Gender</label>
                    <select name="gender" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">—</option>
                        <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                        <option value="Female" @selected(old('gender') === 'Female')>Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+95 9…"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Address</label>
                    <input type="text" name="address" value="{{ old('address') }}" placeholder="Township, City"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Role</label>
                <select name="role" x-model="role" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($roles as $r)
                        <option value="{{ $r }}" @selected(old('role') === $r)>{{ ucwords(str_replace('_', ' ', $r)) }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="['admin','principal','vp_academic','registrar','teacher','treasurer','hr_office'].includes(role)" class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-neutral-100 pt-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Staff ID number</label>
                    <input type="text" name="staff_id_number" value="{{ old('staff_id_number') }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Department (optional)</label>
                    <select name="department_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">—</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Joined date</label>
                    <input type="date" name="joined_date" value="{{ old('joined_date') }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>

            <div class="border-t border-neutral-100 pt-4" x-data="{ mode: '{{ old('password') ? 'password' : 'invite' }}', password: '{{ old('password') }}' }">
                <label class="block text-sm font-semibold mb-2">Account activation</label>
                <div class="flex flex-wrap gap-2 mb-3">
                    <label class="cursor-pointer">
                        <input type="radio" value="invite" x-model="mode" class="peer sr-only">
                        <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-semibold border border-neutral-200 text-neutral-600 peer-checked:bg-[#1F573D] peer-checked:text-white peer-checked:border-[#1F573D]">Send setup email (pending until they choose a password)</span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" value="password" x-model="mode" class="peer sr-only">
                        <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-semibold border border-neutral-200 text-neutral-600 peer-checked:bg-[#1F573D] peer-checked:text-white peer-checked:border-[#1F573D]">Set a password now (active immediately)</span>
                    </label>
                </div>
                <div x-show="mode === 'password'" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Password</label>
                        <input type="text" name="password" x-model="password" :required="mode === 'password'" minlength="8"
                            :disabled="mode !== 'password'"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Confirm password</label>
                        <input type="text" name="password_confirmation" :required="mode === 'password'" minlength="8"
                            :disabled="mode !== 'password'"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm font-mono">
                    </div>
                    <button type="button"
                        @click="password = Array.from(crypto.getRandomValues(new Uint8Array(12))).map(b => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%'[b % 60]).join(''); $el.closest('form').querySelector('[name=password_confirmation]').value = password"
                        class="text-sm font-semibold text-neutral-600 hover:underline justify-self-start pb-2.5">Generate strong password</button>
                </div>
                <p class="text-xs text-neutral-500 mt-2" x-show="mode === 'invite'">The account is created as <span class="font-semibold">Pending</span> and becomes Active when the person completes the emailed setup link.</p>
                <p class="text-xs text-neutral-500 mt-2" x-show="mode === 'password'" x-cloak>Hand the password over in person — it is never emailed.</p>
            </div>

            <p class="text-xs text-neutral-500">
                Guardian and Student accounts are created here as login accounts only; linking them to the actual
                student/guardian record is done from the Registrar portal.
            </p>

            <div class="flex gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Create user</button>
                <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
            </div>
        </form>
    </x-card>
</x-app-layout>
