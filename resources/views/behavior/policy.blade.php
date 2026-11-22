@php
    $fields = ['warning' => 'Warning', 'final_warning' => 'FinalWarning', 'expulsion_review' => 'ExpulsionReview'];
    $labels = \App\Models\BehaviorFlag::LABELS;
@endphp
<x-app-layout title="Behavior policy" subtitle="A student is at a stage when their score is at or below that stage’s threshold. Scores start at 100 each school year." :badge="$canManage ? 'VP' : 'Read only'">
    <x-card :title="$canManage ? 'Warning thresholds' : 'Warning thresholds (view only)'"
            subtitle="Whole numbers from 1 to 99, each stage lower than the one before. A change never rewrites history: existing flags and notices stay, and students are checked against the new numbers from their next deduction or restoration.">
        <form method="POST" action="{{ route('behavior.policy.update') }}"
              x-data="behaviorPolicy({{ json_encode(collect($fields)->mapWithKeys(fn ($stage, $f) => [$f => old($f, $thresholds[$stage])])) }}, '{{ route('behavior.policy.preview') }}', {{ $canManage ? 'true' : 'false' }})"
              class="space-y-5">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach ($fields as $field => $stage)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-semibold mb-1">{{ $labels[$stage] }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" min="1" max="99" step="1" required
                               x-model.number="values.{{ $field }}" @input.debounce.400ms="preview()"
                               @disabled(! $canManage)
                               class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-lg font-semibold tabular-nums disabled:text-neutral-600">
                        <p class="text-xs text-neutral-500 mt-1">Now: {{ $thresholds[$stage] }}</p>
                    </div>
                @endforeach
            </div>

            @if ($canManage)
                <div class="rounded-xl border border-neutral-200 overflow-hidden" aria-live="polite">
                    <p class="px-4 py-2.5 bg-neutral-50 border-b border-neutral-200 text-xs font-bold uppercase tracking-wider text-neutral-500">Before you save — effect on students this school year</p>
                    <template x-if="error"><p class="px-4 py-3 text-sm text-danger" x-text="error"></p></template>
                    <template x-if="loading && ! stages"><p class="px-4 py-3 text-sm text-neutral-500">Working it out…</p></template>
                    <template x-if="stages && ! error">
                        <div>
                            <div class="hidden sm:block">
                                <table class="w-full text-sm">
                                    <thead><tr class="text-left text-neutral-500 border-b border-neutral-100">
                                        <th class="py-2 px-4 font-semibold">Stage</th>
                                        <th class="py-2 px-2 font-semibold text-right">Threshold</th>
                                        <th class="py-2 px-2 font-semibold text-right">Flagged now</th>
                                        <th class="py-2 px-2 font-semibold text-right">Newly flagged</th>
                                        <th class="py-2 px-4 font-semibold text-right">Unflagged</th>
                                    </tr></thead>
                                    <tbody>
                                        <template x-for="s in Object.values(stages)" :key="s.label">
                                            <tr class="border-b border-neutral-100 last:border-0">
                                                <td class="py-2 px-4 font-semibold" x-text="s.label"></td>
                                                <td class="py-2 px-2 text-right tabular-nums"><span x-text="s.current"></span> → <span class="font-semibold" x-text="s.proposed"></span></td>
                                                <td class="py-2 px-2 text-right tabular-nums" x-text="s.open"></td>
                                                <td class="py-2 px-2 text-right tabular-nums font-semibold" :class="s.newly_flagged ? 'text-danger' : ''" x-text="s.newly_flagged"></td>
                                                <td class="py-2 px-4 text-right tabular-nums font-semibold" :class="s.unflagged ? 'text-success' : ''" x-text="s.unflagged"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <ul class="sm:hidden divide-y divide-neutral-100">
                                <template x-for="s in Object.values(stages)" :key="s.label">
                                    <li class="px-4 py-3 text-sm">
                                        <p class="font-semibold" x-text="s.label + ': ' + s.current + ' → ' + s.proposed"></p>
                                        <p class="text-neutral-600">Flagged now <span x-text="s.open"></span> · newly flagged <span class="font-semibold" x-text="s.newly_flagged"></span> · unflagged <span class="font-semibold" x-text="s.unflagged"></span></p>
                                    </li>
                                </template>
                            </ul>
                            <p class="px-4 py-2.5 text-xs text-neutral-500 border-t border-neutral-100">These changes happen at each student’s next deduction or restoration, not on save.</p>
                        </div>
                    </template>
                </div>

                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark disabled:opacity-50" :disabled="invalid">Save thresholds</button>
            @endif
        </form>
    </x-card>

    @unless ($canManage)
        <p class="text-sm text-neutral-500">Only the Vice Principal can change these thresholds.</p>
    @endunless
</x-app-layout>

