<x-app-layout title="VP Academic Dashboard" subtitle="Department-level academic performance and two-key approvals (first key)." badge="VP Academic" role="vp_academic">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-stat-tile label="Promotion batches awaiting review" color="yellow">{{ $pendingPromotions }}</x-stat-tile>
        <x-stat-tile label="Transcripts awaiting review" color="yellow">{{ $pendingTranscripts }}</x-stat-tile>
    </div>

    <x-card title="Department performance" subtitle="Average assessed score across all recorded grades.">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Department</th>
                    <th class="py-2 font-semibold">Average</th>
                    <th class="py-2 font-semibold">Graded entries</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departmentPerformance as $row)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $row->department }}</td>
                        <td class="py-2.5 font-semibold">{{ $row->average !== null ? $row->average.'%' : '—' }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $row->gradedCount }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-neutral-400">No academic departments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="flex gap-3">
        <a href="{{ route('vp_academic.approvals.index') }}" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Review approvals</a>
        <a href="{{ route('vp_academic.fees.index') }}" class="bg-neutral-900 text-white font-semibold rounded-lg px-5 py-2.5 text-sm">View imported fee records (read-only)</a>
    </div>
</x-app-layout>
