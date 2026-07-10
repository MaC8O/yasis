<x-app-layout title="Board Reports" subtitle="Student numbers and background summaries for the School Board." badge="Board-ready" role="principal">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Enrollment Summary" subtitle="Total and per-department student counts for the current year.">
            <a href="{{ route('principal.board-reports.enrollment-pdf') }}" target="_blank" class="inline-block border border-[#1F573D] text-[#1F573D] font-semibold rounded-lg px-5 py-2.5 text-sm">Generate PDF</a>
        </x-card>

        <x-card title="Religious Background Summary" subtitle="Distribution of student religious background for the Board.">
            <a href="{{ route('principal.board-reports.religious-pdf') }}" target="_blank" class="inline-block border border-[#1F573D] text-[#1F573D] font-semibold rounded-lg px-5 py-2.5 text-sm">Generate PDF</a>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Enrollment by department" subtitle="As reported to the School Board.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Department</th>
                        <th class="py-2 font-semibold">Students</th>
                        <th class="py-2 font-semibold">New this year</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enrollment as $row)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $row->department }}</td>
                            <td class="py-2.5">{{ $row->total }}</td>
                            <td class="py-2.5 text-green-700">+{{ $row->newThisYear }}</td>
                        </tr>
                    @endforeach
                    <tr class="font-bold">
                        <td class="py-2.5">Total</td>
                        <td class="py-2.5">{{ $totalEnrollment }}</td>
                        <td class="py-2.5"></td>
                    </tr>
                </tbody>
            </table>
        </x-card>

        <x-card title="Religious background" subtitle="Student distribution ({{ $totalEnrollment }} total).">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Background</th>
                        <th class="py-2 font-semibold">Students</th>
                        <th class="py-2 font-semibold">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($religious as $row)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $row->background }}</td>
                            <td class="py-2.5">{{ $row->total }}</td>
                            <td class="py-2.5">{{ $row->share }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-neutral-400">No enrolled students yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</x-app-layout>
