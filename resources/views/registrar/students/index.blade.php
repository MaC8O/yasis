<x-app-layout title="Student Records" subtitle="Search, filter, and manage active student profiles." badge="Registrar" role="registrar">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name or ID"
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Department</label>
                <select name="department" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department'] ?? '') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Filter</button>
                <a href="{{ route('registrar.students.create') }}" class="flex-1 text-center bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm">New Student</a>
                <a href="{{ route('registrar.students.import') }}" class="flex-1 text-center border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2.5 text-sm">Bulk Import</a>
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Active" color="blue">{{ number_format($stats['active']) }}</x-stat-tile>
        <x-stat-tile label="New this year" color="blue">{{ number_format($stats['newThisYear']) }}</x-stat-tile>
        <x-stat-tile label="Missing guardian" color="yellow">{{ number_format($stats['missingGuardian']) }}</x-stat-tile>
        <x-stat-tile label="Transferred" color="pink">{{ number_format($stats['transferred']) }}</x-stat-tile>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Student ID</th>
                        <th class="py-2 font-semibold">Name</th>
                        <th class="py-2 font-semibold">Department / Section</th>
                        <th class="py-2 font-semibold">Guardian</th>
                        <th class="py-2 font-semibold">Status</th>
                        <th class="py-2 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $student->student_id_number }}</td>
                            <td class="py-2.5">{{ $student->first_name }} {{ $student->last_name }}</td>
                            <td class="py-2.5 text-neutral-500">{{ $student->department?->name }}{{ optional($student->enrollments->first())->section ? ' · '.$student->enrollments->first()->section->name : '' }}</td>
                            <td class="py-2.5 text-neutral-500">{{ $student->guardiansList() ?: '—' }}</td>
                            <td class="py-2.5">
                                <x-badge :color="$student->enrollment_status === 'Enrolled' ? 'green' : ($student->enrollment_status === 'Graduated' ? 'blue' : 'pink')">
                                    {{ $student->enrollment_status }}
                                </x-badge>
                            </td>
                            <td class="py-2.5 text-right">
                                <a href="{{ route('registrar.students.show', $student) }}" class="text-xs font-semibold text-[#1F573D] hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-neutral-400">No students found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $students->links() }}</div>
    </x-card>
</x-app-layout>
