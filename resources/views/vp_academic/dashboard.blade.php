<x-app-layout title="VP Academic Dashboard" subtitle="Department-level academic performance and two-key approvals (first key)." badge="VP Academic" role="vp_academic">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-stat-tile label="Promotion batches awaiting review" color="yellow">{{ $pendingPromotions }}</x-stat-tile>
        <x-stat-tile label="Transcripts awaiting review" color="yellow">{{ $pendingTranscripts }}</x-stat-tile>
    </div>

    <x-card title="Department performance" subtitle="Average assessed score across all recorded grades.">
        <x-chart.bar-list :max="100"
            :items="collect($departmentPerformance)->filter(fn ($row) => $row->average !== null)
                ->map(fn ($row) => ['label' => $row->department, 'value' => $row->average, 'display' => $row->average.'%'])" />
        @php $ungraded = collect($departmentPerformance)->filter(fn ($row) => $row->average === null); @endphp
        @if ($ungraded->isNotEmpty())
            <p class="text-xs text-neutral-400 mt-3">No grades recorded yet: {{ $ungraded->pluck('department')->implode(', ') }}.</p>
        @endif
    </x-card>

    <div class="flex gap-3">
        <a href="{{ route('vp_academic.approvals.index') }}" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Review approvals</a>
        <a href="{{ route('vp_academic.fees.index') }}" class="bg-neutral-900 text-white font-semibold rounded-lg px-5 py-2.5 text-sm">View imported fee records (read-only)</a>
    </div>
</x-app-layout>
