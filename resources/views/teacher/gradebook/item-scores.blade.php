@php
    $max = (float) $assessment->max_score;
    $maxLabel = rtrim(rtrim(number_format($max, 2), '0'), '.');
    $locked = (bool) $term?->is_locked;
    $initial = $roster->mapWithKeys(fn ($s) => [$s->id => old('scores.'.$s->id, $grades->get($s->id)?->score !== null ? rtrim(rtrim(number_format((float) $grades->get($s->id)->score, 2), '0'), '.') : '')]);
@endphp
<x-app-layout :title="$assessment->name"
              :subtitle="$section->name.' · '.($subject?->name ?? '').' · '.($term?->name ?? '').' · '.$category->name.' ('.rtrim(rtrim(number_format($category->weight_pct, 2), '0'), '.').'%)'"
              badge="Teacher · Enter scores" role="teacher">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $backUrl }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-neutral-500 hover:underline">← Back to gradebook</a>
        <x-badge color="purple">{{ $assessment->type_label }}</x-badge>
        <x-badge color="blue">Out of {{ $maxLabel }}</x-badge>
        <span class="flex-1"></span>
        @if ($previous)
            <a href="{{ route('teacher.gradebook.assessments.scores', $previous) }}" class="inline-flex items-center min-h-[44px] px-3 rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50" title="{{ $previous->name }}">← Previous item</a>
        @endif
        @if ($next)
            <a href="{{ route('teacher.gradebook.assessments.scores', $next) }}" class="inline-flex items-center min-h-[44px] px-3 rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50" title="{{ $next->name }}">Next item →</a>
        @endif
    </div>

    @if ($locked)
        <div class="bg-warning-soft border border-warning-line text-warning text-sm rounded-xl px-5 py-3">
            <span class="font-semibold">{{ $term->name }} is locked by the Principal.</span>
            Scores are read-only — use “Request a grade change” on the gradebook page for corrections.
        </div>
    @endif

    <form method="POST" action="{{ route('teacher.gradebook.assessments.scores.save', $assessment) }}"
          x-data="itemScores(@js($initial), {{ $max }})" @submit="dirty = false" class="space-y-0">
        @csrf @method('PUT')
        <x-card :title="'Class scores — '.$roster->count().' '.Str::plural('student', $roster->count())"
                subtitle="Type a score and press Enter to move to the next student (Tab works too). Leave a box empty to skip that student. One Save stores the whole class.">
            <div class="mb-3 flex flex-wrap items-center gap-3 text-sm">
                <span class="font-semibold" x-text="`Entered ${entered()} / {{ $roster->count() }}`"></span>
                <span class="text-danger font-semibold" x-show="overMax() > 0" x-cloak x-text="`${overMax()} above the maximum`"></span>
                <span class="text-neutral-400" x-show="dirty" x-cloak>Unsaved changes</span>
            </div>

            @if ($roster->isEmpty())
                <p class="text-sm text-neutral-400">No students are placed in {{ $section->name }}.</p>
            @else
                <ol class="divide-y divide-neutral-100">
                    @foreach ($roster as $i => $student)
                        @php $err = $errors->first('scores.'.$student->id) ?: $errors->first('scores.'.$assessment->id.'.'.$student->id); @endphp
                        <li class="py-2 flex items-center gap-3">
                            <span class="w-6 shrink-0 text-right text-xs text-neutral-400">{{ $i + 1 }}</span>
                            <label for="s-{{ $student->id }}" class="flex-1 min-w-0">
                                <span class="block font-semibold text-sm truncate">{{ $student->name }}</span>
                                <span class="block text-xs text-neutral-400">{{ $student->student_id_number }}</span>
                                @if ($err) <span class="block text-xs text-danger">{{ $err }}</span> @endif
                            </label>
                            <div class="shrink-0 flex items-center gap-2">
                                <input id="s-{{ $student->id }}" type="number" inputmode="decimal" step="0.01" min="0" max="{{ $max }}"
                                       name="scores[{{ $student->id }}]" value="{{ $initial[$student->id] }}" x-model="scores[{{ $student->id }}]"
                                       @input="dirty = true" @keydown.enter.prevent="moveNext($event)"
                                       @disabled($locked) data-score-input
                                       :class="isOver({{ $student->id }}) ? 'border-danger bg-danger-soft' : 'border-neutral-200 bg-neutral-50'"
                                       class="w-24 sm:w-28 rounded-lg border px-3 py-2.5 text-right text-sm min-h-[44px] {{ $err ? 'border-danger' : '' }}">
                                <span class="w-16 text-xs text-neutral-500 tabular-nums" x-text="pct({{ $student->id }})"></span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @unless ($locked || $roster->isEmpty())
                <div class="sticky bottom-0 -mx-5 px-5 sm:-mx-8 sm:px-8 mt-4 py-3 bg-white border-t border-neutral-100 flex flex-col sm:flex-row gap-3 sm:items-center">
                    <button type="submit" x-ref="save" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-6 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save scores for the class</button>
                    @if ($next)
                        <span class="text-xs text-neutral-400">After saving, use “Next item →” to continue with {{ $next->name }}.</span>
                    @endif
                </div>
            @endunless
        </x-card>
    </form>

    <script>
        function itemScores(initial, max) {
            return {
                scores: { ...initial }, max, dirty: false,
                init() {
                    window.addEventListener('beforeunload', (e) => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
                    this.$nextTick(() => document.querySelector('[data-score-input]:not([disabled])')?.focus());
                },
                val(id) { const v = this.scores[id]; return v === '' || v === null || v === undefined ? null : Number(v); },
                isOver(id) { const v = this.val(id); return v !== null && (v > this.max || v < 0); },
                pct(id) { const v = this.val(id); return v === null || !this.max ? '' : (Math.round(v / this.max * 1000) / 10) + '%'; },
                entered() { return Object.keys(this.scores).filter(id => this.val(id) !== null).length; },
                overMax() { return Object.keys(this.scores).filter(id => this.isOver(id)).length; },
                moveNext(e) {
                    const inputs = [...document.querySelectorAll('[data-score-input]:not([disabled])')];
                    const next = inputs[inputs.indexOf(e.target) + 1];
                    next ? (next.focus(), next.select()) : this.$refs.save?.focus();
                },
            };
        }
    </script>
</x-app-layout>
