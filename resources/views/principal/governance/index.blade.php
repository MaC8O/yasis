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

    <x-card title="Grade levels" subtitle="The school's grade levels in order. Grading, subjects, report cards and fees follow these. Set by the Admin (Grade Levels); shown here read-only.">
        @if ($gradeLevels->isEmpty())
            <p class="text-sm text-neutral-400">No grade levels are set up yet.</p>
        @else
            <ol class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
                @foreach ($gradeLevels as $level)
                    <li class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm {{ $level->isArchived() ? 'opacity-60' : '' }}">
                        <span><span class="font-semibold">{{ $level->name }}</span> <span class="text-xs text-neutral-400">({{ $level->code }})</span></span>
                        <span class="text-xs text-neutral-500">
                            @if ($level->isArchived()) Retired @else {{ $level->sections_count }} {{ Str::plural('section', $level->sections_count) }} @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-card>

    <x-card title="Grading schemes" subtitle="How each grade level's results become a class of pass. Set by the Admin; shown here read-only.">
        @if ($gradingSchemes->isEmpty())
            <p class="text-sm text-neutral-400">No grading schemes are set up yet.</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($gradingSchemes as $scheme)
                    <div class="rounded-xl border border-neutral-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-sm">{{ $scheme->name }}</p>
                            <x-badge color="blue">{{ $scheme->resultLabel() }}</x-badge>
                        </div>
                        <p class="mt-1 text-xs text-neutral-500">{{ $scheme->gradeLevels->isEmpty() ? 'No grade level uses it.' : $scheme->gradeLevels->pluck('name')->implode(', ') }}</p>
                        @if ($scheme->pending_question || $scheme->flagged_bands_count)
                            <p class="mt-2 text-xs font-semibold text-warning">Uses defaults that need confirmation from the school.</p>
                        @endif
                        <table class="mt-3 w-full text-xs">
                            <thead>
                                <tr class="text-left text-neutral-400 border-b border-neutral-200">
                                    <th class="py-1 font-semibold">Class of pass</th>
                                    <th class="py-1 font-semibold text-right">{{ $scheme->resultLabel() }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($scheme->bands as $band)
                                    <tr class="border-b border-neutral-100 last:border-0">
                                        <td class="py-1.5 font-semibold">{{ $band->label }}</td>
                                        <td class="py-1.5 text-neutral-600 text-right whitespace-nowrap">{{ number_format((float) $band->min, $scheme->result_decimals) }}–{{ number_format((float) $band->max, $scheme->result_decimals) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('principal.grading-schemes.index') }}" class="mt-4 inline-flex items-center min-h-[44px] text-sm font-semibold text-info hover:underline">See the full schemes: subject marks, letters, points and grade values</a>
        @endif
    </x-card>
</x-app-layout>
