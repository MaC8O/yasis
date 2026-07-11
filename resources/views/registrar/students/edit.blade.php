<x-app-layout title="Edit Student Profile" :subtitle="$student->student_id_number" badge="Registrar" role="registrar">
    <x-card>
        <form method="POST" action="{{ route('registrar.students.update', $student) }}" enctype="multipart/form-data"
              x-data="{ photo: null }" class="grid grid-cols-2 gap-4">
            @csrf @method('PUT')
            <div class="col-span-2 flex items-center gap-4">
                <div class="w-24 h-24 rounded-xl border border-neutral-200 bg-neutral-50 overflow-hidden shrink-0 flex items-center justify-center">
                    <template x-if="photo"><img :src="photo" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!photo">
                        @if ($student->photo_path)
                            <img src="{{ Storage::url($student->photo_path) }}" alt="{{ $student->name }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-[11px] text-neutral-400 text-center px-2 leading-tight">No photo</span>
                        @endif
                    </template>
                </div>
                <div>
                    <label class="inline-block cursor-pointer bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2 text-sm">
                        {{ $student->photo_path ? 'Replace photo…' : 'Upload photo…' }}
                        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="sr-only"
                               @change="photo = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                    </label>
                    <p class="text-xs text-neutral-400 mt-1.5">JPG, PNG or WebP · up to 10 MB · cropped to a square.</p>
                </div>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-semibold mb-1">Full name</label>
                <input type="text" name="name" value="{{ old('name', $student->name) }}" required
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
