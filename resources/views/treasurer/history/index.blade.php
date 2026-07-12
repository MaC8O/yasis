<x-app-layout title="Import History" subtitle="Audit trail of imports and published batches — with one-click revert of a bad batch." badge="Sun account, not Sun Plus" role="treasurer">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Batches" color="blue">{{ $stats['total'] }}</x-stat-tile>
        <x-stat-tile label="Published" color="green">{{ $stats['published'] }}</x-stat-tile>
        <x-stat-tile label="Needs review" color="yellow">{{ $stats['needsReview'] }}</x-stat-tile>
    </div>

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Batch</th>
                    <th class="py-2 font-semibold">Source file</th>
                    <th class="py-2 font-semibold">Period</th>
                    <th class="py-2 font-semibold">Rows</th>
                    <th class="py-2 font-semibold">Matched</th>
                    <th class="py-2 font-semibold">Status</th>
                    <th class="py-2 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">#{{ $batch->id }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $batch->source_file }}</td>
                        <td class="py-2.5">{{ $batch->period }}</td>
                        <td class="py-2.5">{{ $batch->row_count }}</td>
                        <td class="py-2.5">{{ $batch->matched_count }}</td>
                        <td class="py-2.5"><x-badge :color="$batch->is_published ? 'green' : 'yellow'">{{ $batch->is_published ? 'Published' : 'Needs review' }}</x-badge></td>
                        <td class="py-2.5">
                            <div class="flex gap-3 text-xs font-semibold">
                                <a href="{{ route('treasurer.validate.index', ['batch' => $batch->id]) }}" class="text-[#1F573D] hover:underline">View</a>
                                <form method="POST" action="{{ route('treasurer.history.revert', $batch) }}" onsubmit="return confirm('Revert this batch? This removes exactly this batch\'s {{ $batch->row_count }} records.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-700 hover:underline">Revert</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-4 text-neutral-400">No import batches yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
