<x-app-layout title="Add Staff" subtitle="Onboard a new employee — teaching or non-teaching — with role, department, and start date." badge="HR Office" role="hr_office">
    <x-card>
        <form method="POST" action="{{ route('hr_office.staff.store') }}" class="space-y-5" x-data="{ portal: false }">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Full name</label>
                    <input type="text" name="name" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Staff ID number</label>
                    <input type="text" name="staff_id_number" required placeholder="S013" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Job title</label>
                    <input type="text" name="job_title" required placeholder="Receptionist, Bus Driver, Canteen Head..." class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Department</label>
                    <select name="department_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">—</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Joined date</label>
                    <input type="date" name="joined_date" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="portal_access" value="1" x-model="portal">
                Needs an ISMS portal login (Admin, Principal, VP Academic, Registrar, Teacher, Treasurer, or HR Office)
            </label>

            <div x-show="portal" class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-neutral-100 pt-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Portal role</label>
                    <select name="portal_role" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($portalRoles as $role)
                            <option value="{{ $role }}">{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>
            <p class="text-xs text-neutral-500">
                Without portal access, this creates a personnel-only record (no ISMS login) — matching the school's
                non-portal staff (receptionist, maintenance, canteen, transportation, etc.).
            </p>

            <div class="flex gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Add staff</button>
                <a href="{{ route('hr_office.staff.index') }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
            </div>
        </form>
    </x-card>
</x-app-layout>
