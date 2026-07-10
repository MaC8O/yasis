<x-app-layout title="Absence Corrections" subtitle="Review recent attendance classifications and correct them where needed — the Registrar's senior correction path." badge="Registrar" role="registrar">
    @if ($needsReview->isNotEmpty())
        <x-card title="Needs review ({{ $needsReview->count() }})" subtitle="Marked Absent although a guardian absence notice covers the day — these were probably meant to be Excused.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Date</th>
                            <th class="py-2 font-semibold">Student</th>
                            <th class="py-2 font-semibold">Section</th>
                            <th class="py-2 font-semibold">Marked</th>
                            <th class="py-2 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($needsReview as $record)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5">{{ $record->attendance_date->format('M j, Y') }}</td>
                                <td class="py-2.5">{{ $record->student->name }}</td>
                                <td class="py-2.5 text-neutral-500">{{ $record->section->name }}</td>
                                <td class="py-2.5"><x-badge color="pink">Absent</x-badge></td>
                                <td class="py-2.5 text-right">
                                    <form method="POST" action="{{ route('registrar.attendance-corrections.update', $record) }}" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="Excused">
                                        <button type="submit" class="text-xs font-semibold text-[#1F573D] hover:underline">Mark Excused</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Section</label>
                <select name="section" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All sections</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected($filters['section'] == $section->id)>{{ $section->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    <option value="">All statuses</option>
                    @foreach (['Present', 'Absent', 'Tardy', 'Excused'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Filter</button>
        </form>
    </x-card>

    <x-card title="Attendance records" subtitle="Every correction is written to the audit log with the original and corrected classification.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Date</th>
                        <th class="py-2 font-semibold">Student</th>
                        <th class="py-2 font-semibold">Section</th>
                        <th class="py-2 font-semibold">Status</th>
                        <th class="py-2 font-semibold">Notice</th>
                        <th class="py-2 font-semibold">Recorded by</th>
                        <th class="py-2 font-semibold">Correct to</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $record->attendance_date->format('M j, Y') }}</td>
                            <td class="py-2.5">{{ $record->student->name }}</td>
                            <td class="py-2.5 text-neutral-500">{{ $record->section->name }}</td>
                            <td class="py-2.5">
                                <x-badge :color="$record->status === 'Present' ? 'green' : ($record->status === 'Excused' ? 'blue' : ($record->status === 'Tardy' ? 'yellow' : 'pink'))">
                                    {{ $record->status }}
                                </x-badge>
                            </td>
                            <td class="py-2.5 text-neutral-500">{{ $record->absenceNotice ? 'Linked' : '—' }}</td>
                            <td class="py-2.5 text-neutral-500">{{ $record->recordedBy?->user?->name ?? '—' }}</td>
                            <td class="py-2.5">
                                <form method="POST" action="{{ route('registrar.attendance-corrections.update', $record) }}" class="flex gap-2 items-center">
                                    @csrf @method('PUT')
                                    <select name="status" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                        @foreach (['Present', 'Absent', 'Tardy', 'Excused'] as $status)
                                            <option value="{{ $status }}" @selected($record->status === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-xs font-semibold text-[#1F573D] hover:underline">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-neutral-400">No attendance records in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $records->links() }}</div>
    </x-card>
</x-app-layout>
