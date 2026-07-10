<x-app-layout title="Promotion Batches" subtitle="Prepare year-end promotion for VP + Principal two-key co-approval." badge="Registrar" role="registrar">
    @if ($promotionWindowOpen)
        <x-card title="Start a new batch">
            <form method="GET" class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-semibold mb-1">Section</label>
                    <select id="section-select" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($sections as $section)
                            <option value="{{ route('registrar.promotions.create', $section) }}">{{ $section->name }} ({{ $section->department->name }})</option>
                        @endforeach
                    </select>
                </div>
                <a id="prepare-link" href="{{ $sections->first() ? route('registrar.promotions.create', $sections->first()) : '#' }}"
                    class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Prepare batch</a>
            </form>
            <script>
                document.getElementById('section-select')?.addEventListener('change', function () {
                    document.getElementById('prepare-link').href = this.value;
                });
            </script>
        </x-card>
    @else
        <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-xl px-5 py-3">
            <span class="font-semibold">The promotion window is closed.</span>
            New promotion batches cannot be prepared until the Principal opens the window (Setup &amp; Controls on the Principal portal).
        </div>
    @endif

    <x-card>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">From section</th>
                    <th class="py-2 font-semibold">Students</th>
                    <th class="py-2 font-semibold">Prepared by</th>
                    <th class="py-2 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $batch->fromSection->name }}</td>
                        <td class="py-2.5">{{ $batch->items->count() }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $batch->preparedBy->user->name ?? '—' }}</td>
                        <td class="py-2.5">
                            <x-badge :color="$batch->status === 'Applied' ? 'green' : ($batch->status === 'Rejected' ? 'pink' : 'yellow')">{{ str($batch->status)->replace('_', ' ') }}</x-badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No promotion batches prepared yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Two-key co-approval">
        <p class="text-sm text-neutral-500">
            A prepared batch is <strong>Pending</strong> until the VP Academic reviews and the Principal co-approves
            (two-key), mirroring the school's committee-decided promotion. Approval happens on the VP Academic and
            Principal portals; once both keys are given the promotion is applied automatically. The Registrar's role
            ends at preparation.
        </p>
    </x-card>
</x-app-layout>
