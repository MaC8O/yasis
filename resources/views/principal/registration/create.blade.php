<x-app-layout title="Assist Student Registration" subtitle="Create a student record alongside the Registrar during admissions peaks." badge="Principal" role="principal">
    <x-card>
        <form method="POST" action="{{ route('principal.registration.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Student ID</label>
                <input type="text" name="student_id_number" required placeholder="YAS-2026-0001" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Admission date</label>
                <input type="date" name="admission_date" value="{{ now()->toDateString() }}" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">First name</label>
                <input type="text" name="first_name" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Last name</label>
                <input type="text" name="last_name" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold mb-1">Department</label>
                <select name="department_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <p class="sm:col-span-2 text-xs text-neutral-500">
                This creates the core student profile. The Registrar will complete guardian linking, section
                placement, and document collection.
            </p>
            <div class="sm:col-span-2 flex gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Register student</button>
                <a href="{{ route('principal.dashboard') }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
            </div>
        </form>
    </x-card>
</x-app-layout>
