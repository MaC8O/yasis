<x-app-layout title="Edit Student Profile" :subtitle="$student->student_id_number" badge="Registrar" role="registrar">
    <x-card>
        <form method="POST" action="{{ route('registrar.students.update', $student) }}" class="grid grid-cols-2 gap-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-semibold mb-1">First name</label>
                <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}" required
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Last name</label>
                <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}" required
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Birth date</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $student->date_of_birth?->toDateString()) }}"
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Gender</label>
                <select name="gender" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="Male" @selected($student->gender === 'Male')>Male</option>
                    <option value="Female" @selected($student->gender === 'Female')>Female</option>
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Department</label>
                <select name="department_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected($student->department_id === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2 flex gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Save changes</button>
                <a href="{{ route('registrar.students.show', $student) }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
            </div>
        </form>
    </x-card>
</x-app-layout>
