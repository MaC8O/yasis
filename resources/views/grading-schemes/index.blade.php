@php
    use App\Models\GradingScheme;
    use App\Support\GradingBands;

    $places = fn (int $d) => $d === 0 ? 'whole numbers' : $d.' decimal '.($d === 1 ? 'place' : 'places');
    $num = fn ($v, int $d) => number_format((float) $v, $d, '.', '');
    $valueText = fn ($v) => $v === null ? '—' : ($scheme?->result_basis === 'gpa' ? number_format((float) $v, 2) : GradingBands::display(GradingBands::hundredths($v)));
    $flaggedBands = $scheme ? collect($bandSets)->flatten(1)->filter(fn ($b) => $b->pending_question) : collect();
    $questions = $scheme
        ? collect($scheme->pendingQuestions())->merge($flaggedBands->flatMap(fn ($b) => $b->pendingQuestions()))->unique()->sort(SORT_NATURAL)->values()
        : collect();
    $passMark = $scheme ? $bandSets['mark']->reject->is_fail->min('min') : null;
    $unassigned = $levels->whereNull('grading_scheme_id');
@endphp
<x-app-layout title="Grading Schemes" :subtitle="$readOnly
        ? 'How marks become letters, points or grade values, and the class of pass, for each grade level. Set by the Admin; shown here read-only.'
        : 'How marks become letters, points or grade values, and the class of pass, for each grade level.'"
    :badge="$readOnly ? 'Principal' : 'Admin'" :role="$portal">

    <div class="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
        These schemes are stored for the result calculation that comes next (phase 4). Until then, the grades teachers, students and guardians see still use the department grade scale.
        @unless ($readOnly) It is set on <a href="{{ route('admin.grade-scale.index') }}" class="font-semibold text-info underline">Grade Scale (current, until phase 4)</a>. @endunless
    </div>

    <x-card title="Scheme">
        <nav aria-label="Grading schemes" class="flex flex-wrap gap-2">
            @foreach ($schemes as $s)
                @php $isFlagged = $s->pending_question || $s->flagged_bands_count; @endphp
                <a href="{{ route($portal.'.grading-schemes.index', ['scheme' => $s->code]) }}"
                   @if ($scheme && $s->id === $scheme->id) aria-current="page" @endif
                   class="inline-flex items-center gap-1 min-h-[44px] px-4 rounded-lg border text-sm font-semibold {{ $scheme && $s->id === $scheme->id ? 'border-brand bg-brand text-white' : 'border-neutral-200 hover:bg-neutral-50' }}">
                    {{ $s->name }}
                    @if ($isFlagged) <span class="text-xs {{ $scheme && $s->id === $scheme->id ? 'text-white' : 'text-warning' }}" title="Uses a default that needs confirmation from the school">●</span> @endif
                </a>
            @endforeach
        </nav>
        <p class="mt-3 text-xs text-neutral-400">● = uses a default that needs confirmation from the school.</p>
    </x-card>

    @if ($scheme)
        <x-card :title="$scheme->name" :subtitle="'Used by '.($scheme->gradeLevels->isEmpty() ? 'no grade level' : $scheme->gradeLevels->pluck('name')->implode(', ')).'.'">
            @if ($questions->isNotEmpty())
                <div class="mb-4 rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning">
                    This scheme uses defaults that <span class="font-semibold">need confirmation from the school</span> ({{ $questions->implode(', ') }} in the rules file).
                    @unless ($readOnly) When the school confirms, edit the settings or the band set and tick “Confirmed by the school”. @endunless
                </div>
            @endif

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                <div>
                    <dt class="text-neutral-500">Result</dt>
                    <dd class="font-semibold">{{ GradingScheme::RESULT_BASES[$scheme->result_basis] }} (0 to {{ $num($scheme->result_max, $scheme->result_decimals) }})</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Pass mark per subject</dt>
                    <dd class="font-semibold">{{ $passMark === null ? '—' : $num($passMark, $scheme->mark_decimals) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Rounding before the band lookup</dt>
                    <dd class="font-semibold">
                        Marks to {{ $places($scheme->mark_decimals) }}; {{ $scheme->result_basis === 'gpa' ? 'the GPA' : 'the '.Str::lower($scheme->resultLabel()) }} to {{ $places($scheme->result_decimals) }}.
                        {{ GradingScheme::ROUNDING_MODES[$scheme->rounding_mode] }}.
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Failing a subject</dt>
                    <dd class="font-semibold">{{ GradingScheme::FAIL_RULES[$scheme->subject_fail_rule] }}</dd>
                </div>
            </dl>
            @if ($scheme->pending_question)
                <p class="mt-3"><span class="rounded-full bg-tint-yellow text-tint-yellow-ink px-2.5 py-1 text-xs font-semibold">Settings need confirmation from the school ({{ implode(', ', $scheme->pendingQuestions()) }})</span></p>
            @endif

            @unless ($readOnly)
                <div x-data="{ editing: {{ $errors->any() && old('_set') === 'settings' ? 'true' : 'false' }} }" class="mt-4">
                    <button type="button" @click="editing = !editing" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-info hover:bg-neutral-50">Edit settings</button>
                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.grading-schemes.update', $scheme) }}" class="mt-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                        @csrf @method('PUT')
                        <input type="hidden" name="_set" value="settings">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="scheme-name" class="block text-sm font-semibold mb-1">Name</label>
                                <input id="scheme-name" type="text" name="name" maxlength="60" required value="{{ old('_set') === 'settings' ? old('name') : $scheme->name }}"
                                       class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label for="scheme-rounding" class="block text-sm font-semibold mb-1">Rounding</label>
                                <select id="scheme-rounding" name="rounding_mode" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                                    @foreach (GradingScheme::ROUNDING_MODES as $mode => $label)
                                        <option value="{{ $mode }}" @selected((old('_set') === 'settings' ? old('rounding_mode') : $scheme->rounding_mode) === $mode)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="scheme-fail" class="block text-sm font-semibold mb-1">Failing a subject</label>
                                <select id="scheme-fail" name="subject_fail_rule" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                                    @foreach (GradingScheme::FAIL_RULES as $rule => $label)
                                        <option value="{{ $rule }}" @selected((old('_set') === 'settings' ? old('subject_fail_rule') : $scheme->subject_fail_rule) === $rule)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-neutral-500">The decimal places values are rounded to are set with each band set below, because the bands have to fit them.</p>
                        @if ($scheme->pending_question)
                            <label class="mt-3 flex items-center gap-2 text-sm min-h-[44px]">
                                <input type="hidden" name="confirmed" value="0">
                                <input type="checkbox" name="confirmed" value="1" class="rounded border-neutral-300 text-brand">
                                Confirmed by the school (clears the {{ implode(', ', $scheme->pendingQuestions()) }} flag)
                            </label>
                        @endif
                        <button type="submit" class="mt-3 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save settings</button>
                    </form>
                </div>
            @endunless
        </x-card>

        @foreach ($bandSets as $kind => $bands)
            @php
                $decimals = $scheme->decimals($kind);
                $hasValues = $kind === 'mark' && $scheme->usesBandValues();
                $setFlags = $bands->filter(fn ($b) => $b->pending_question);
                $mine = old('_set') === $kind;
                $initial = $mine
                    ? collect(old('bands', []))->values()
                    : $bands->map(fn ($b) => [
                        'label' => $b->label,
                        'min' => $num($b->min, $decimals),
                        'max' => $num($b->max, $decimals),
                        'value' => $b->value === null ? '' : ($scheme->result_basis === 'gpa' ? number_format((float) $b->value, 2) : GradingBands::display(GradingBands::hundredths($b->value))),
                        'is_fail' => $b->is_fail,
                    ])->values();
                $initial = $initial->map(fn ($b, $i) => array_merge(['label' => '', 'min' => '', 'max' => '', 'value' => '', 'is_fail' => false], (array) $b, ['_k' => $i]));
                $subtitle = $kind === 'mark'
                    ? 'Each subject\'s mark, rounded to '.$places($decimals).', falls in one band.'.($hasValues ? ' The band gives the subject\'s '.Str::lower($scheme->valueLabel()).'.' : ' A fail band is below the pass mark.')
                    : 'The '.($scheme->result_basis === 'gpa' ? 'GPA' : Str::lower($scheme->resultLabel())).', rounded to '.$places($decimals).', gives the class of pass.';
            @endphp
            <x-card :title="GradingScheme::BAND_KINDS[$kind]" :subtitle="$subtitle">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-400 border-b border-neutral-200">
                                <th class="py-2 pr-4 font-semibold">{{ $kind === 'mark' ? 'Letter' : 'Class' }}</th>
                                <th class="py-2 pr-4 font-semibold">{{ $kind === 'mark' ? 'Mark' : $scheme->resultLabel() }}</th>
                                @if ($hasValues) <th class="py-2 pr-4 font-semibold">{{ $scheme->valueLabel() }}</th> @endif
                                <th class="py-2 font-semibold">Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bands as $band)
                                <tr class="border-b border-neutral-100 last:border-0">
                                    <td class="py-2.5 pr-4 font-semibold">{{ $band->label }}</td>
                                    <td class="py-2.5 pr-4 text-neutral-600 whitespace-nowrap">{{ $num($band->min, $decimals) }}–{{ $num($band->max, $decimals) }}</td>
                                    @if ($hasValues) <td class="py-2.5 pr-4 text-neutral-600">{{ $valueText($band->value) }}</td> @endif
                                    <td class="py-2.5">
                                        <span class="rounded-full px-2.5 py-1 text-xs {{ $band->is_fail ? 'bg-tint-pink text-tint-pink-ink' : 'bg-tint-green text-tint-green-ink' }}">{{ $band->is_fail ? 'Fail' : 'Pass' }}</span>
                                        @if ($band->pending_question) <span class="ml-1 rounded-full bg-tint-yellow text-tint-yellow-ink px-2.5 py-1 text-xs font-semibold">Needs confirmation from the school ({{ implode(', ', $band->pendingQuestions()) }})</span> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-neutral-400">No bands yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @unless ($readOnly)
                    <div x-data="{ editing: {{ $errors->any() && $mine ? 'true' : 'false' }}, bands: @js($initial), next: {{ $initial->count() }} }" class="mt-4">
                        <button type="button" @click="editing = !editing" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-info hover:bg-neutral-50">Edit {{ Str::lower(GradingScheme::BAND_KINDS[$kind]) }} bands</button>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.grading-schemes.bands', [$scheme, $kind]) }}" class="mt-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                            @csrf @method('PUT')
                            <input type="hidden" name="_set" value="{{ $kind }}">
                            <p class="text-sm text-neutral-600 mb-3">
                                The bands must cover 0 to the top with no gaps and no overlaps, with the fail bands lowest. Saving replaces the whole set; the previous set is kept in the history.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="{{ $kind }}-decimals" class="block text-sm font-semibold mb-1">Round to</label>
                                    <select id="{{ $kind }}-decimals" name="decimals" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                                        @foreach ([0, 1, 2] as $d)
                                            <option value="{{ $d }}" @selected((int) ($mine ? old('decimals') : $decimals) === $d)>{{ ucfirst($places($d)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($kind === 'result' && $scheme->hasEditableResultMax())
                                    <div>
                                        <label for="result-max" class="block text-sm font-semibold mb-1">Top of the range <span class="font-normal text-neutral-400">(highest possible {{ $scheme->result_basis === 'gpa' ? 'GPA' : Str::lower($scheme->resultLabel()) }})</span></label>
                                        <input id="result-max" type="text" inputmode="decimal" name="result_max" maxlength="6" required value="{{ $mine ? old('result_max') : $num($scheme->result_max, $decimals) }}"
                                               class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                                    </div>
                                @else
                                    <p class="text-sm text-neutral-500 sm:pt-7">Range: 0 to {{ $kind === 'mark' ? 100 : $num($scheme->result_max, 0) }}.</p>
                                @endif
                            </div>

                            <ol class="space-y-3">
                                <template x-for="(band, i) in bands" :key="band._k">
                                    <li class="grid grid-cols-2 sm:grid-cols-12 gap-2 items-end rounded-lg border border-neutral-200 bg-white p-3">
                                        <div class="col-span-2 sm:col-span-3">
                                            <label :for="'{{ $kind }}-label-' + band._k" class="block text-xs font-semibold mb-1">{{ $kind === 'mark' ? 'Letter' : 'Class' }}</label>
                                            <input :id="'{{ $kind }}-label-' + band._k" type="text" maxlength="20" :name="`bands[${i}][label]`" x-model="band.label"
                                                   class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label :for="'{{ $kind }}-min-' + band._k" class="block text-xs font-semibold mb-1">From</label>
                                            <input :id="'{{ $kind }}-min-' + band._k" type="text" inputmode="decimal" maxlength="6" :name="`bands[${i}][min]`" x-model="band.min"
                                                   class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label :for="'{{ $kind }}-max-' + band._k" class="block text-xs font-semibold mb-1">To</label>
                                            <input :id="'{{ $kind }}-max-' + band._k" type="text" inputmode="decimal" maxlength="6" :name="`bands[${i}][max]`" x-model="band.max"
                                                   class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                        </div>
                                        @if ($hasValues)
                                            <div class="sm:col-span-2">
                                                <label :for="'{{ $kind }}-value-' + band._k" class="block text-xs font-semibold mb-1">{{ $scheme->valueLabel() }}</label>
                                                <input :id="'{{ $kind }}-value-' + band._k" type="text" inputmode="decimal" maxlength="5" :name="`bands[${i}][value]`" x-model="band.value"
                                                       class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                            </div>
                                        @endif
                                        <label class="flex items-center gap-2 text-sm min-h-[44px] {{ $hasValues ? 'sm:col-span-1' : 'sm:col-span-3' }}">
                                            <input type="checkbox" value="1" :name="`bands[${i}][is_fail]`" x-model="band.is_fail" class="rounded border-neutral-300 text-brand">
                                            Fail
                                        </label>
                                        <div class="sm:col-span-2 sm:text-right">
                                            <button type="button" @click="bands.splice(i, 1)" :aria-label="'Remove band ' + (band.label || (i + 1))"
                                                    class="min-h-[44px] px-3 rounded-lg border border-neutral-200 text-sm font-semibold text-warning hover:bg-neutral-50">Remove</button>
                                        </div>
                                    </li>
                                </template>
                            </ol>
                            <button type="button" @click="bands.push({ label: '', min: '', max: '', value: '', is_fail: false, _k: next++ })"
                                    class="mt-3 min-h-[44px] px-4 rounded-lg border border-neutral-200 bg-white text-sm font-semibold hover:bg-neutral-50">Add band</button>

                            @if ($setFlags->isNotEmpty())
                                <label class="mt-3 flex items-center gap-2 text-sm min-h-[44px]">
                                    <input type="hidden" name="confirmed" value="0">
                                    <input type="checkbox" name="confirmed" value="1" class="rounded border-neutral-300 text-brand">
                                    Confirmed by the school (clears the {{ $setFlags->flatMap(fn ($b) => $b->pendingQuestions())->unique()->implode(', ') }} flag on {{ $setFlags->pluck('label')->implode(', ') }})
                                </label>
                            @endif
                            <div class="mt-3">
                                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save {{ Str::lower(GradingScheme::BAND_KINDS[$kind]) }} bands</button>
                            </div>
                        </form>
                    </div>
                @endunless
            </x-card>
        @endforeach
    @endif

    <x-card title="Grade levels" :subtitle="$readOnly ? 'The scheme each grade level uses.' : 'The scheme each grade level uses. Changing it changes how that level\'s results are calculated.'">
        @if ($unassigned->isNotEmpty())
            <div class="mb-4 rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning">
                {{ $unassigned->pluck('name')->implode(', ') }} {{ $unassigned->count() === 1 ? 'has' : 'have' }} no grading scheme, so {{ $unassigned->count() === 1 ? 'its' : 'their' }} results can't be calculated.
            </div>
        @endif
        <ul class="divide-y divide-neutral-100">
            @foreach ($levels as $l)
                <li class="py-2.5 flex flex-wrap items-center gap-x-4 gap-y-2 {{ $l->isArchived() ? 'opacity-60' : '' }}">
                    <span class="w-40 font-semibold text-sm">{{ $l->name }} @if ($l->isArchived()) <span class="text-xs font-normal text-neutral-400">(retired)</span> @endif</span>
                    @if ($readOnly)
                        <span class="text-sm text-neutral-600">{{ $l->gradingScheme?->name ?? 'No grading scheme' }}</span>
                    @else
                        <form method="POST" action="{{ route('admin.grading-schemes.assign', $l) }}" class="flex flex-wrap items-center gap-2"
                              data-level="{{ $l->name }}" data-from="{{ $l->gradingScheme?->name ?? 'no scheme' }}" data-current="{{ $l->grading_scheme_id }}"
                              onsubmit="var s = this.elements.grading_scheme_id; return s.value === this.dataset.current || confirm('Change ' + this.dataset.level + ' from ' + this.dataset.from + ' to ' + s.options[s.selectedIndex].text + '? This changes how its results are calculated.');">
                            @csrf @method('PUT')
                            <label for="level-scheme-{{ $l->id }}" class="sr-only">Grading scheme for {{ $l->name }}</label>
                            <select id="level-scheme-{{ $l->id }}" name="grading_scheme_id" class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm min-h-[44px]">
                                @unless ($l->grading_scheme_id) <option value="">Choose…</option> @endunless
                                @foreach ($schemes as $s)
                                    <option value="{{ $s->id }}" @selected($l->grading_scheme_id === $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-info hover:bg-neutral-50">Change</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</x-app-layout>
