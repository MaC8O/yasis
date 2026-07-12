<x-app-layout title="Register New Student" subtitle="Create student profile, guardian link, and enrollment." badge="Registrar" role="registrar">
    <form method="POST" action="{{ route('registrar.students.store') }}" enctype="multipart/form-data" x-data="{ guardianMode: 'existing', photo: null }">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Student Profile" subtitle="Basic personal details.">
                <div class="flex items-center gap-4 mb-5">
                    <div class="w-24 h-24 rounded-xl border border-neutral-200 bg-neutral-50 overflow-hidden shrink-0 flex items-center justify-center">
                        <template x-if="photo"><img :src="photo" alt="" class="w-full h-full object-cover"></template>
                        <template x-if="!photo"><span class="text-[11px] text-neutral-400 text-center px-2 leading-tight">No photo</span></template>
                    </div>
                    <div>
                        <label class="inline-block cursor-pointer bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2 text-sm">
                            Upload photo…
                            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="sr-only"
                                   @change="photo = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                        </label>
                        <p class="text-xs text-neutral-400 mt-1.5">JPG, PNG or WebP · up to 10 MB · cropped to a square.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Student ID</label>
                        <input type="text" name="student_id_number" value="{{ old('student_id_number') }}" required placeholder="YAS-2026-0001"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Admission date</label>
                        <input type="date" name="admission_date" value="{{ old('admission_date', now()->toDateString()) }}" required
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold mb-1">Full name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Saw Htoo Aung"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Birth date</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Gender</label>
                        <select name="gender" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
            </x-card>

            <x-card title="Guardian & Contact" subtitle="Link a guardian to the record.">
                <div class="flex gap-4 mb-4 text-sm font-semibold">
                    <label class="flex items-center gap-1.5"><input type="radio" name="guardian_mode" value="existing" x-model="guardianMode" checked> Existing guardian</label>
                    <label class="flex items-center gap-1.5"><input type="radio" name="guardian_mode" value="new" x-model="guardianMode"> New guardian</label>
                    <label class="flex items-center gap-1.5"><input type="radio" name="guardian_mode" value="none" x-model="guardianMode"> Link later</label>
                </div>

                <div x-show="guardianMode === 'existing'">
                    <label class="block text-sm font-semibold mb-1">Guardian</label>
                    <select name="guardian_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">Select guardian</option>
                        @foreach ($guardians as $guardian)
                            <option value="{{ $guardian->id }}">{{ $guardian->user?->name }} ({{ $guardian->user?->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="guardianMode === 'new'" class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold mb-1">Guardian name</label>
                        <input type="text" name="guardian_name" value="{{ old('guardian_name') }}"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Relationship</label>
                        <input type="text" name="guardian_relationship" value="{{ old('guardian_relationship') }}" placeholder="Mother"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Phone</label>
                        <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-semibold mb-1">Email</label>
                        <input type="email" name="guardian_email" value="{{ old('guardian_email') }}"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                </div>
            </x-card>

            <x-card title="Enrollment Setup" subtitle="Assign academic placement.">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Department</label>
                        <select name="department_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Section (active year)</label>
                        <select name="section_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            <option value="">Assign later</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}">{{ $section->name }} ({{ $section->department->name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-card>

            <x-card title="Notes">
                <p class="text-sm text-neutral-500">
                    Bulk-imported fee records, grades, and attendance become available once the student is enrolled
                    into a section. A portal login for the student is created separately once needed.
                </p>
            </x-card>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Register Student</button>
            <a href="{{ route('registrar.students.index') }}" class="text-sm font-semibold text-neutral-500 self-center">Cancel</a>
        </div>
    </form>
</x-app-layout>
