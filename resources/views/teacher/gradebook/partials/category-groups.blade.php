{{-- Categories as collapsible groups of gradable items. $editable = term not locked. --}}
@php
    $types = \App\Models\Assessment::TYPES;
    $rosterIds = $roster->pluck('id');
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<div x-data="gradebookGroups(@js($categories->pluck('id')), '{{ $section->id }}-{{ $subject->id }}-{{ $term?->id }}')" class="space-y-3 mb-6">
    @if ($categories->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-neutral-500 mr-1">Show:</span>
            <button type="button" @click="filter = ''" :class="filter === '' ? 'bg-brand text-white border-brand' : 'bg-white'"
                    class="min-h-[36px] px-3 rounded-full border border-neutral-200 text-xs font-semibold">All types</button>
            @foreach ($types as $key => $label)
                <button type="button" @click="filter = '{{ $key }}'" :class="filter === '{{ $key }}' ? 'bg-brand text-white border-brand' : 'bg-white'"
                        class="min-h-[36px] px-3 rounded-full border border-neutral-200 text-xs font-semibold">{{ $label }}</button>
            @endforeach
            <span class="flex-1"></span>
            <button type="button" @click="setAll(true)" class="min-h-[36px] text-xs font-semibold text-brand hover:underline">Expand all</button>
            <button type="button" @click="setAll(false)" class="min-h-[36px] text-xs font-semibold text-brand hover:underline">Collapse all</button>
        </div>
    @endif

    @forelse ($categories as $category)
        @php
            $items = $category->assessments->sortBy('id');
            $typeCounts = $items->countBy('type');
        @endphp
        <div class="border border-neutral-200 rounded-xl">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3">
                <button type="button" @click="toggle({{ $category->id }})" :aria-expanded="isOpen({{ $category->id }})"
                        class="flex-1 min-w-0 flex items-center gap-2 text-left min-h-[44px]">
                    <span class="w-5 text-neutral-400 transition-transform" :class="isOpen({{ $category->id }}) ? 'rotate-90' : ''" aria-hidden="true">▶</span>
                    <span class="font-semibold">{{ $category->name }}</span>
                    <x-badge color="blue">{{ $num($category->weight_pct) }}% weight</x-badge>
                    @if ($category->isTemplate()) <span class="text-xs text-neutral-400" title="Set by the school">fixed</span> @endif
                    <span class="text-xs text-neutral-400">
                        {{ $items->count() }} {{ Str::plural('item', $items->count()) }}@if ($typeCounts->isNotEmpty()) ·
                            {{ $typeCounts->map(fn ($n, $t) => $n.' '.Str::lower($types[$t] ?? $t))->join(', ') }}@endif
                    </span>
                </button>
                @if ($editable && ! $category->isTemplate())
                    <form method="POST" action="{{ route('teacher.gradebook.categories.destroy', $category) }}"
                          data-confirm="Archive the “{{ $category->name }}” category? Its items and scores are kept but stop counting; you can restore it while the term is open." onsubmit="return confirm(this.dataset.confirm)">
                        @csrf @method('DELETE')
                        <button type="submit" class="min-h-[44px] text-xs font-semibold text-danger hover:underline">Archive category</button>
                    </form>
                @endif
            </div>

            <div x-show="isOpen({{ $category->id }})" x-cloak class="border-t border-neutral-100 px-4 py-3 space-y-2">
                @if ($category->isAttendance())
                    <p class="text-sm text-neutral-600">Calculated from the attendance records: days attended (Present and Tardy) over the student's recorded days, with Excused days left out. It shows read-only in the grid below.</p>
                @else
                @forelse ($items as $assessment)
                    @php $scored = $assessment->grades->whereIn('student_id', $rosterIds)->count(); @endphp
                    <div x-data="{ edit: false }" x-show="filter === '' || filter === '{{ $assessment->type }}'"
                         class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                        <div x-show="!edit" class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span class="font-medium">{{ $assessment->name }}</span>
                            <span class="inline-flex items-center rounded-full bg-tint-purple text-tint-purple-ink px-2 py-0.5 text-xs font-medium">{{ $assessment->type_label }}</span>
                            <span class="text-xs text-neutral-500">out of {{ $num($assessment->max_score) }}</span>
                            <span class="text-xs {{ $roster->count() && $scored >= $roster->count() ? 'text-success' : 'text-neutral-400' }}">scored {{ $scored }}/{{ $roster->count() }}</span>
                            <span class="flex-1"></span>
                            <a href="{{ route('teacher.gradebook.assessments.scores', $assessment) }}"
                               class="inline-flex items-center min-h-[36px] px-3 rounded-lg bg-brand text-white text-xs font-semibold hover:bg-brand-dark">{{ $editable ? 'Enter scores' : 'View scores' }}</a>
                            @if ($editable)
                                <button type="button" @click="edit = true" class="min-h-[36px] text-xs font-semibold text-neutral-500 hover:underline">Edit</button>
                                @include('teacher.gradebook.partials.move-item', ['item' => $assessment, 'from' => $category])
                                <form method="POST" action="{{ route('teacher.gradebook.assessments.destroy', $assessment) }}"
                                      data-confirm="Archive “{{ $assessment->name }}”? Its {{ $scored }} {{ Str::plural('score', $scored) }} are kept but stop counting; you can restore it while the term is open." onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="min-h-[36px] text-xs font-semibold text-danger hover:underline">Archive</button>
                                </form>
                            @endif
                        </div>
                        @if ($editable)
                            <form x-show="edit" x-cloak method="POST" action="{{ route('teacher.gradebook.assessments.update', $assessment) }}" class="flex flex-wrap items-end gap-2">
                                @csrf @method('PATCH')
                                <label class="text-xs">Name
                                    <input type="text" name="name" value="{{ $assessment->name }}" required maxlength="255" class="block w-40 rounded border border-neutral-200 px-2 py-1.5 text-sm">
                                </label>
                                <label class="text-xs">Type
                                    <select name="type" class="block rounded border border-neutral-200 px-2 py-1.5 text-sm">
                                        @foreach ($types as $key => $label)
                                            <option value="{{ $key }}" @selected($assessment->type === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs">Max
                                    <input type="number" step="0.01" min="1" name="max_score" value="{{ $num($assessment->max_score) }}" required class="block w-20 rounded border border-neutral-200 px-2 py-1.5 text-sm">
                                </label>
                                <button type="submit" class="min-h-[36px] text-xs font-semibold text-brand hover:underline">Save</button>
                                <button type="button" @click="edit = false" class="min-h-[36px] text-xs text-neutral-400 hover:underline">Cancel</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-neutral-400 italic">No items yet{{ $editable ? ' — add the first one below.' : '.' }}</p>
                @endforelse

                @if ($editable)
                    <form method="POST" action="{{ route('teacher.gradebook.assessments.store') }}" class="flex flex-wrap gap-2 items-end pt-2">
                        @csrf
                        <input type="hidden" name="category_id" value="{{ $category->id }}">
                        <label class="text-xs font-semibold text-neutral-500">New item
                            <input type="text" name="name" required maxlength="255" placeholder="e.g. Quiz 3" class="block w-44 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-normal">
                        </label>
                        <label class="text-xs font-semibold text-neutral-500">Type
                            <select name="type" class="block rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-normal">
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}" @selected(\App\Models\Assessment::guessType($category->name) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-neutral-500">Max pts
                            <input type="number" step="0.01" min="1" name="max_score" required value="100" class="block w-20 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-normal">
                        </label>
                        <button type="submit" class="min-h-[40px] border border-brand text-brand font-semibold rounded-lg px-3 py-2 text-sm hover:bg-brand hover:text-white transition">+ Add item</button>
                    </form>
                @endif
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-neutral-400">No categories yet{{ $editable ? ' — add your first one below (e.g. Quiz 30%, Test 40%, Homework 30%).' : '.' }}</p>
    @endforelse
</div>

@once
    <script>
        function gradebookGroups(ids, scope) {
            const key = 'gradebook-open-' + scope;
            let saved = null;
            try { saved = JSON.parse(localStorage.getItem(key)); } catch (e) {}
            return {
                filter: '',
                // First visit: only the first category is open, so long lists start compact.
                open: Array.isArray(saved) ? saved : ids.slice(0, 1),
                isOpen(id) { return this.filter !== '' || this.open.includes(id); },
                toggle(id) { this.open = this.open.includes(id) ? this.open.filter(x => x !== id) : [...this.open, id]; this.persist(); },
                setAll(on) { this.open = on ? [...ids] : []; this.persist(); },
                persist() { try { localStorage.setItem(key, JSON.stringify(this.open)); } catch (e) {} },
            };
        }
    </script>
@endonce
