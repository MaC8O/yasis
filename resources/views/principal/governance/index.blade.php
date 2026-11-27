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
                @if ($emptyCategories)
                    <div class="-mt-1 rounded-xl border border-warning-line bg-warning-soft p-3 text-sm text-warning" data-empty-categories>
                        <p class="font-semibold">Before you lock {{ $currentTerm->name }}: {{ count($emptyCategories) }} {{ count($emptyCategories) === 1 ? 'gradebook has' : 'gradebooks have' }} an empty category</p>
                        <p class="mt-0.5 text-xs">A subject with no Examinations items gets no final mark, so its students have no overall result. Other empty categories are left out of the weighting.</p>
                        <ul class="mt-2 max-h-48 overflow-y-auto list-disc pl-5 text-xs space-y-0.5">
                            @foreach ($emptyCategories as $row)
                                <li>{{ $row['section'] }} · {{ $row['subject'] }}: {{ implode(', ', $row['categories']) }}</li>
                            @endforeach
                        </ul>
                        <label class="mt-2 flex items-center gap-2 text-sm min-h-[44px]">
                            <input type="hidden" name="acknowledge_empty_categories" value="0">
                            <input type="checkbox" name="acknowledge_empty_categories" value="1" class="w-5 h-5">
                            Lock anyway
                        </label>
                    </div>
                @endif
                @if ($currentTerm?->is_locked && $activeFreeze)
                    <p class="-mt-2 text-xs text-neutral-500">Results frozen {{ $activeFreeze->frozen_at->format('j M Y, H:i') }}: report cards and past-term views read these stored results. Unlocking lifts the freeze (it is kept) and the term is calculated live again.</p>
                @elseif ($currentTerm?->is_locked)
                    <p class="-mt-2 text-xs text-warning">Locked before frozen results existed: run the freeze-locked-terms command so this term's results are stored.</p>
                @elseif ($previousFreeze)
                    <fieldset class="-mt-1 rounded-xl border border-neutral-200 bg-neutral-50 p-3 space-y-2" x-data="{ rules: '{{ old('relock_rules', 'previous_freeze') }}' }">
                        <legend class="px-1 text-xs font-semibold text-neutral-600">{{ $currentTerm->name }} was locked before. When you lock it again:</legend>
                        <label class="flex items-start gap-2 text-sm min-h-[44px]">
                            <input type="radio" name="relock_rules" value="previous_freeze" x-model="rules" class="mt-1">
                            <span>Use the rules the term was locked with <span class="block text-xs text-neutral-500">The marks are recalculated from today's grades; bands and rounding stay as they were at {{ $previousFreeze->frozen_at->format('j M Y') }}.</span></span>
                        </label>
                        <label class="flex items-start gap-2 text-sm min-h-[44px]">
                            <input type="radio" name="relock_rules" value="current" x-model="rules" class="mt-1">
                            <span>Recalculate with the current rules <span class="block text-xs text-neutral-500">For example after the school answers Q10. The audit log keeps the rules before and after.</span></span>
                        </label>
                        <div x-show="rules === 'current'" x-cloak>
                            <label for="relock-reason" class="block text-xs font-semibold mb-1">Why (required)</label>
                            <textarea id="relock-reason" name="relock_reason" rows="2" maxlength="1000" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">{{ old('relock_reason') }}</textarea>
                        </div>
                    </fieldset>
                @endif

                <label class="flex items-center justify-between">
                    <span>
                        <span class="block font-semibold text-sm">Transcript & certificate issuance</span>
                        <span class="block text-xs text-neutral-500">Enable transcript two-key release and Statement of Marks printing by the Registrar.</span>
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
                @if ($unconfirmedSections)
                    <div class="-mt-1 rounded-xl border border-warning-line bg-warning-soft p-3 text-sm text-warning" data-unconfirmed-sections>
                        <p class="font-semibold">{{ count($unconfirmedSections) }} {{ count($unconfirmedSections) === 1 ? 'class has' : 'classes have' }} report cards the class teacher hasn't confirmed</p>
                        <p class="mt-0.5 text-xs">You can still release; the unconfirmed cards print without the class teacher's name. Releasing records your sign-off on every card.</p>
                        <ul class="mt-2 max-h-40 overflow-y-auto list-disc pl-5 text-xs">
                            @foreach ($unconfirmedSections as $row)
                                <li>{{ $row['section'] }}: {{ $row['unconfirmed'] }} of {{ $row['total'] }} unconfirmed</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

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
