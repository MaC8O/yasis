<x-app-layout title="Setup & Controls" subtitle="Governance controls are Principal-owned. Technical settings are Admin-managed and shown here for reference." badge="Governance" role="principal">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Governance controls" subtitle="Principal-owned decisions that gate academic workflows.">
            <form method="POST" action="{{ route('principal.governance.update') }}" class="space-y-4">
                @csrf @method('PUT')

                <label class="flex items-center justify-between">
                    <span>
                        <span class="block font-semibold text-sm">Promotion window</span>
                        <span class="block text-xs text-neutral-500">Open/close year-end promotion.</span>
                    </span>
                    <input type="checkbox" name="promotion_window_open" value="1" @checked($settings['promotion_window_open'] === '1') class="w-5 h-5">
                </label>

                <label class="flex items-center justify-between">
                    <span>
                        <span class="block font-semibold text-sm">Grade lock — current term</span>
                        <span class="block text-xs text-neutral-500">{{ $currentTerm?->name ?? 'No active term' }} · freeze grades once finalised.</span>
                    </span>
                    <input type="checkbox" name="grade_lock" value="1" @checked($currentTerm?->is_locked) class="w-5 h-5">
                </label>

                <label class="flex items-center justify-between">
                    <span>
                        <span class="block font-semibold text-sm">Transcript & certificate issuance</span>
                        <span class="block text-xs text-neutral-500">Enable transcript two-key release to the Registrar.</span>
                    </span>
                    <input type="checkbox" name="transcript_issuance_enabled" value="1" @checked($settings['transcript_issuance_enabled'] === '1') class="w-5 h-5">
                </label>

                <label class="flex items-center justify-between">
                    <span>
                        <span class="block font-semibold text-sm">Release term results</span>
                        <span class="block text-xs text-neutral-500">Publish {{ $currentTerm?->name ?? 'term' }} report cards to families.</span>
                    </span>
                    <input type="checkbox" name="results_released" value="1" @checked($currentTerm?->results_released) class="w-5 h-5">
                </label>

                <label class="flex items-center justify-between">
                    <span>
                        <span class="block font-semibold text-sm">Principal may assist registration</span>
                        <span class="block text-xs text-neutral-500">Allow the Principal to help the Registrar register students.</span>
                    </span>
                    <input type="checkbox" name="principal_may_assist_registration" value="1" @checked($settings['principal_may_assist_registration'] === '1') class="w-5 h-5">
                </label>

                <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-6 py-3 text-sm hover:bg-brand-dark transition-colors">Save controls</button>
            </form>
        </x-card>

        <x-card title="Academic calendar" subtitle="{{ $activeYear?->year_label ?? 'No active year' }}, four terms of nine weeks each.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Term</th>
                        <th class="py-2 font-semibold">Start</th>
                        <th class="py-2 font-semibold">End</th>
                        <th class="py-2 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($terms as $term)
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="py-2.5">{{ $term->name }}</td>
                            <td class="py-2.5">{{ $term->start_date->format('d M Y') }}</td>
                            <td class="py-2.5">{{ $term->end_date->format('d M Y') }}</td>
                            <td class="py-2.5">
                                @if ($term->is_locked)
                                    <x-badge color="pink">Locked</x-badge>
                                @elseif ($term->id === $currentTerm?->id)
                                    <x-badge color="green">Current</x-badge>
                                @else
                                    <x-badge color="blue">Open</x-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-neutral-400">No academic year set up yet — see Admin &gt; Academic Year.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
    </div>

    <x-card title="Grade scale" subtitle="How term scores become letter grades in each academic department. Set by the Admin (Grade Scale); shown here read-only so you can see what report cards will use.">
        @php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); @endphp
        @if ($gradeScales->isEmpty())
            <p class="text-sm text-neutral-400">No academic departments are set up yet.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach ($gradeScales as $scale)
                    <div class="rounded-xl border border-neutral-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <p class="font-semibold text-sm">{{ $scale['department']->name }}</p>
                            <x-badge :color="$scale['hasGpa'] ? 'blue' : 'neutral'">{{ $scale['hasGpa'] ? 'GPA scale' : 'Descriptive (no GPA)' }}</x-badge>
                        </div>
                        @if ($scale['bands']->isEmpty())
                            <p class="text-xs text-warning">No grade bands defined — ask the Admin to set this department's scale.</p>
                        @else
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-neutral-400 border-b border-neutral-200">
                                        <th class="py-1 font-semibold">Letter</th>
                                        <th class="py-1 font-semibold">Score</th>
                                        <th class="py-1 font-semibold text-right">GPA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($scale['bands'] as $band)
                                        <tr class="border-b border-neutral-100 last:border-0">
                                            <td class="py-1.5 font-semibold">{{ $band['letter'] }}</td>
                                            <td class="py-1.5 text-neutral-600">{{ $fmt($band['min']) }}–{{ $fmt($band['max']) }}%</td>
                                            <td class="py-1.5 text-neutral-600 text-right">{{ $band['gpa'] === null ? '—' : number_format((float) $band['gpa'], 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-neutral-400">Administrative departments (Finance, HR, Canteen, …) have no students, so they have no grade scale.</p>
        @endif
    </x-card>
</x-app-layout>
