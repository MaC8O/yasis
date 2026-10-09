<x-app-layout title="Student Records" subtitle="Search, filter, and manage active student profiles." badge="Registrar" role="registrar">
    <x-card>
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold mb-1">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name, student ID, or class (e.g. Grade 9-A)"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Department</label>
                    <select name="department" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department'] ?? '') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Class</label>
                    <select name="section" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <option value="">All classes</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected(($filters['section'] ?? '') == $section->id)>{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-6 py-2.5 text-sm hover:bg-brand-dark transition-colors">Search</button>
                <a href="{{ route('registrar.students.index') }}" class="border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-50 transition-colors">Clear</a>
                <div class="flex-1"></div>
                <a href="{{ route('registrar.students.create') }}" class="text-center bg-neutral-900 text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-neutral-700 transition-colors">New Student</a>
                <a href="{{ route('registrar.students.import') }}" class="text-center border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-neutral-50 transition-colors">Bulk Import</a>
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Active" color="blue">{{ number_format($stats['active']) }}</x-stat-tile>
        <x-stat-tile label="New this year" color="blue">{{ number_format($stats['newThisYear']) }}</x-stat-tile>
        <x-stat-tile label="Missing guardian" color="yellow">{{ number_format($stats['missingGuardian']) }}</x-stat-tile>
        <x-stat-tile label="Transferred" color="pink">{{ number_format($stats['transferred']) }}</x-stat-tile>
    </div>

    <div x-data="masterDetail({{ $panel['student']->id ?? 'null' }})"
         class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">
        {{-- Master list --}}
        <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-neutral-200 bg-neutral-50">
                <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-neutral-500">
                    <x-icon name="student" class="w-4 h-4" /> Students
                    <span class="rounded-full bg-neutral-200 text-neutral-700 px-2 py-0.5 text-[11px] tabular-nums">{{ $students->total() }}</span>
                </p>
                <p class="text-[11px] text-neutral-400 hidden sm:block">Click to view · double-click to open full profile</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 pl-4 pr-2 font-semibold">Student ID</th>
                            <th class="py-2 px-2 font-semibold">Name</th>
                            <th class="py-2 px-2 font-semibold">Department / Class</th>
                            <th class="py-2 pl-2 pr-4 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            @php $section = $student->enrollments->firstWhere('status', 'Active')?->section ?? $student->enrollments->first()?->section; @endphp
                            <tr @click="select({{ $student->id }}, '{{ route('registrar.students.panel', $student) }}')"
                                @dblclick="open('{{ route('registrar.students.show', $student) }}')"
                                :class="selected === {{ $student->id }} ? 'bg-brand-soft shadow-[inset_3px_0_0_var(--color-brand)]' : 'hover:bg-neutral-50'"
                                class="border-b border-neutral-100 last:border-0 cursor-pointer select-none transition-colors">
                                <td class="py-2 pl-4 pr-2 text-neutral-500 tabular-nums whitespace-nowrap">{{ $student->student_id_number }}</td>
                                <td class="py-2 px-2">
                                    <div class="flex items-center gap-2.5">
                                        @if ($student->photo_path)
                                            <img src="{{ Storage::url($student->photo_path) }}" alt="" class="w-8 h-8 rounded-full object-cover border border-neutral-200 shrink-0">
                                        @else
                                            <span class="w-8 h-8 rounded-full bg-brand text-white font-semibold text-[10px] flex items-center justify-center shrink-0">
                                                {{ collect(explode(' ', $student->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                                            </span>
                                        @endif
                                        <button type="button" class="text-left font-medium text-ink hover:underline"
                                                :aria-pressed="selected === {{ $student->id }}">{{ $student->name }}</button>
                                        @if ($student->guardians->isEmpty())
                                            <span class="text-warning" title="No guardian linked"><x-icon name="alert" class="w-3.5 h-3.5" /></span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 px-2 text-neutral-500">{{ $student->department?->name }}{{ $section ? ' · '.$section->name : '' }}</td>
                                <td class="py-2 pl-2 pr-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $student->enrollment_status === 'Enrolled' ? 'text-success' : ($student->enrollment_status === 'Graduated' ? 'text-info' : 'text-danger') }}">
                                        <span class="w-2 h-2 rounded-full bg-current" aria-hidden="true"></span>{{ $student->enrollment_status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 px-4 text-neutral-400">No students found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between gap-4 flex-wrap px-4 py-3 border-t border-neutral-200 bg-neutral-50 text-xs">
                <x-per-page />
                <div>{{ $students->links() }}</div>
            </div>
        </div>

        {{-- Detail panel --}}
        <div x-ref="panel" class="xl:sticky xl:top-6 scroll-mt-6 transition-opacity" :class="loading && 'opacity-50 pointer-events-none'" aria-live="polite">
            @if ($panel)
                @include('registrar.students.panel', $panel)
            @else
                <div class="bg-white rounded-2xl border border-dashed border-neutral-300 px-6 py-14 text-center">
                    <span class="mx-auto w-12 h-12 rounded-full bg-neutral-100 text-neutral-400 flex items-center justify-center">
                        <x-icon name="student" class="w-6 h-6" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-ink">No student selected</p>
                    <p class="mt-1 text-xs text-neutral-500">Click a student to see their details here.<br>Double-click to open the full profile.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
