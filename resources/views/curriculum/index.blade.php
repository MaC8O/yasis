@php
    $hrs = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $active = $offerings->whereNull('archived_at');
    // A student's credit-hour total: counted compulsory hours plus their track's counted hours.
    $compulsoryHours = $active->where('is_compulsory', true)->where('counts_in_result', true)->sum(fn ($o) => (float) $o->credit_hours);
    // What counts for one student: the counted compulsory rows plus one track's counted rows.
    $countedCompulsory = $active->whereNull('track')->where('is_compulsory', true)->where('counts_in_result', true)->count();
    $countedTrack = (int) $active->whereNotNull('track')->where('counts_in_result', true)->groupBy('track')->map->count()->max();
    $trackHours = $active->whereNotNull('track')->where('counts_in_result', true)->groupBy('track')->map(fn ($rows) => $rows->sum(fn ($o) => (float) $o->credit_hours))->sortKeys();
    $flagged = $active->filter(fn ($o) => $o->pending_question);
    $questions = $flagged->flatMap(fn ($o) => $o->pendingQuestions())->unique()->sort(SORT_NATURAL)->values();
@endphp
<x-app-layout title="Curriculum" :subtitle="$readOnly
        ? 'The subjects taught at each grade level, in report-card order. Set by the Admin; shown here read-only.'
        : 'The subjects taught at each grade level, in report-card order: what counts in the result, the Grade 10-12 tracks and credit hours.'"
    :badge="$readOnly ? 'Principal' : 'Admin'" :role="$portal">

    <x-card title="Grade level">
        <nav aria-label="Grade levels" class="flex flex-wrap gap-2">
            @foreach ($levels as $l)
                <a href="{{ route($portal.'.curriculum.index', ['level' => $l->code]) }}"
                   @if ($level && $l->id === $level->id) aria-current="page" @endif
                   class="inline-flex items-center gap-1 min-h-[44px] px-4 rounded-lg border text-sm font-semibold {{ $level && $l->id === $level->id ? 'border-brand bg-brand text-white' : 'border-neutral-200 hover:bg-neutral-50' }} {{ $l->isArchived() ? 'opacity-60' : '' }}">
                    {{ $l->name }}
                    @if ($l->flagged_count) <span class="text-xs {{ $level && $l->id === $level->id ? 'text-white' : 'text-warning' }}" title="{{ $l->flagged_count }} {{ Str::plural('row', $l->flagged_count) }} need confirmation from the school">●</span> @endif
                </a>
            @endforeach
        </nav>
        <p class="mt-3 text-xs text-neutral-400">● = rows using a default that needs confirmation from the school. Nursery has no grade level, so it has no curriculum.</p>
    </x-card>

    @if ($level)
        <x-card :title="$level->name" :subtitle="$active->count().' '.Str::plural('subject', $active->count()).' · '.($countedTrack ? ($countedCompulsory + $countedTrack).' counted per student ('.$countedCompulsory.' compulsory + '.$countedTrack.' from their track)' : $active->where('counts_in_result', true)->count().' counted in the result')
            .($trackHours->isNotEmpty() ? ' · one track per student: '.$trackHours->keys()->implode(' or ') : '')
            .($compulsoryHours && $trackHours->filter()->isNotEmpty() ? ' · a student takes '.$hrs($compulsoryHours + $trackHours->max()).' credit hours ('.$hrs($compulsoryHours).' compulsory + '.$hrs($trackHours->max()).' in their track)' : '')">

            @if ($flagged->isNotEmpty())
                <div class="mb-4 rounded-xl border border-warning-line bg-warning-soft px-4 py-3 text-sm text-warning">
                    {{ $flagged->count() }} {{ $flagged->count() === 1 ? 'row uses' : 'rows use' }} a default that <span class="font-semibold">needs confirmation from the school</span>
                    ({{ $questions->implode(', ') }} in the rules file).
                    @unless ($readOnly) When the school confirms, edit the row and tick “Confirmed by the school”. @endunless
                </div>
            @endif

            @if ($offerings->isEmpty())
                <p class="text-sm text-neutral-400">No subjects in this grade level's curriculum yet.</p>
            @else
                <ol class="divide-y divide-neutral-100">
                    @foreach ($offerings as $o)
                        <li x-data="{ editing: {{ $errors->any() && old('_row') == $o->id ? 'true' : 'false' }} }" class="py-3 {{ $o->isArchived() ? 'opacity-60' : '' }}">
                            <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                                <span class="w-8 pt-0.5 text-sm text-neutral-400">{{ $loop->iteration }}.</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-sm">{{ $o->report_label }}</p>
                                    <p class="text-xs text-neutral-500">{{ $o->subject->name }} · <code>{{ $o->subject->code }}</code></p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5 text-xs">
                                        <span class="rounded-full bg-neutral-100 px-2.5 py-1">{{ $o->is_compulsory ? 'Compulsory' : ($o->track ? 'Track · '.$o->track : 'Optional · slot '.$o->elective_slot) }}</span>
                                        <span class="rounded-full bg-neutral-100 px-2.5 py-1">{{ \App\Models\SubjectOffering::GRADING_MODES[$o->grading_mode] }}</span>
                                        <span class="rounded-full px-2.5 py-1 {{ $o->counts_in_result ? 'bg-tint-green text-tint-green-ink' : 'bg-neutral-100' }}">{{ $o->counts_in_result ? 'Counted' : 'Not counted' }}</span>
                                        @if ($o->credit_hours !== null) <span class="rounded-full bg-neutral-100 px-2.5 py-1">{{ $hrs($o->credit_hours) }} {{ Str::plural('hr', (float) $o->credit_hours) }}</span> @endif
                                        @if ($o->pending_question) <span class="rounded-full bg-tint-yellow text-tint-yellow-ink px-2.5 py-1 font-semibold">Needs confirmation from the school ({{ implode(', ', $o->pendingQuestions()) }})</span> @endif
                                        @if ($o->isArchived()) <span class="rounded-full bg-neutral-200 px-2.5 py-1">Retired</span> @endif
                                    </div>
                                </div>
                                @unless ($readOnly)
                                    <div class="flex flex-wrap items-center gap-2">
                                        @unless ($loop->first)
                                            <form method="POST" action="{{ route('admin.curriculum.move', [$o, 'up']) }}">@csrf
                                                <button type="submit" aria-label="Move {{ $o->report_label }} up" class="min-h-[44px] min-w-[44px] rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50">↑</button>
                                            </form>
                                        @endunless
                                        @unless ($loop->last)
                                            <form method="POST" action="{{ route('admin.curriculum.move', [$o, 'down']) }}">@csrf
                                                <button type="submit" aria-label="Move {{ $o->report_label }} down" class="min-h-[44px] min-w-[44px] rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50">↓</button>
                                            </form>
                                        @endunless
                                        <button type="button" @click="editing = !editing" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-info hover:bg-neutral-50">Edit</button>
                                        @if ($o->isArchived())
                                            <form method="POST" action="{{ route('admin.curriculum.restore', $o) }}">@csrf
                                                <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-brand hover:bg-neutral-50">Restore</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.curriculum.retire', $o) }}"
                                                  data-confirm="Retire {{ $o->report_label }} from {{ $level->name }}? It stays listed and can be restored."
                                                  onsubmit="return confirm(this.dataset.confirm);">@csrf
                                                <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-warning hover:bg-neutral-50">Retire</button>
                                            </form>
                                        @endif
                                    </div>
                                @endunless
                            </div>
                            @unless ($readOnly)
                                <form x-show="editing" x-cloak method="POST" action="{{ route('admin.curriculum.update', $o) }}" class="mt-3 sm:ml-12 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="_row" value="{{ $o->id }}">
                                    @include('curriculum.partials.row-fields', ['row' => $o, 'prefix' => 'e'.$o->id])
                                    @if ($o->pending_question)
                                        <label class="mt-3 flex items-center gap-2 text-sm min-h-[44px]">
                                            <input type="hidden" name="confirmed" value="0">
                                            <input type="checkbox" name="confirmed" value="1" class="rounded border-neutral-300 text-brand">
                                            Confirmed by the school (clears the {{ implode(', ', $o->pendingQuestions()) }} flag)
                                        </label>
                                    @endif
                                    <button type="submit" class="mt-3 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save row</button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>

        @unless ($readOnly)
            <x-card title="Add a subject to {{ $level->name }}" subtitle="It goes to the end of the report card; move it into place afterwards. Subjects come from the VP Academic's subject catalogue.">
                @if ($addable->isEmpty())
                    <p class="text-sm text-neutral-400">Every subject in the catalogue is already in this grade level's curriculum.</p>
                @else
                    <form method="POST" action="{{ route('admin.curriculum.store', $level) }}">
                        @csrf
                        <input type="hidden" name="_row" value="new">
                        <div class="mb-4">
                            <label for="add-subject" class="block text-sm font-semibold mb-1">Subject</label>
                            <select id="add-subject" name="subject_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                                <option value="">Choose…</option>
                                @foreach ($addable as $subject)
                                    <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }} ({{ $subject->code }}{{ $subject->department_id ? '' : ', school-wide' }})</option>
                                @endforeach
                            </select>
                        </div>
                        @include('curriculum.partials.row-fields', ['row' => null, 'prefix' => 'add'])
                        <button type="submit" class="mt-4 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Add subject</button>
                    </form>
                @endif
            </x-card>
        @endunless
    @endif
</x-app-layout>
