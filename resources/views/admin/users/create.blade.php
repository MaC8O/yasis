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
