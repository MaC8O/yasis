@php $allAssessments = $categories->flatMap->assessments; @endphp
<x-app-layout title="Category Gradebook" subtitle="Set up weighted categories and items, enter scores with live results, and add report-card comments — assigned classes only." badge="Teacher · Assigned classes only" role="teacher">
    <x-card>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold mb-1">Class / Subject</label>
                <select name="section" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($assignments as $a)
                        <option value="{{ $a->section_id }}" @selected($a->section_id === $section->id)>{{ $a->section->name }} — {{ $a->subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Term</label>
                <select name="term" onchange="this.form.submit()" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($terms as $t)
                        <option value="{{ $t->id }}" @selected($term && $t->id === $term->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-badge :color="$totalWeight == 100 ? 'green' : 'yellow'">Total weight: {{ rtrim(rtrim(number_format($totalWeight, 2), '0'), '.') }}%</x-badge>
            </div>
        </form>
    </x-card>

    @if ($term?->is_locked)
        <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-xl px-5 py-3">
            <span class="font-semibold">{{ $term->name }} is locked by the Principal.</span>
            This gradebook is read-only — submit a grade-change request below; it needs VP Academic review and Principal co-approval (two-key) before it is applied.
        </div>

        <x-card title="Request a grade change" subtitle="Two-key workflow: VP Academic reviews, the Principal co-approves, then the score is applied and audit-logged.">
            <form method="POST" action="{{ route('teacher.gradebook.change-requests.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1">Assessment</label>
                    <select name="assessment_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($allAssessments as $assessment)
                            <option value="{{ $assessment->id }}">{{ $assessment->name }} (max {{ rtrim(rtrim(number_format($assessment->max_score, 2), '0'), '.') }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Student</label>
                    <select name="student_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($roster as $student)
                            <option value="{{ $student->id }}">{{ $student->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Corrected score</label>
                    <input type="number" name="new_score" step="0.01" min="0" required
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Reason</label>
                    <input type="text" name="reason" required maxlength="255" placeholder="e.g. transcription error"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Submit request</button>
            </form>
        </x-card>
    @endif

    @if (($changeRequests ?? collect())->isNotEmpty())
        <x-card title="My grade-change requests ({{ $term?->name }})" subtitle="Requests are locked once decided; only Pending ones can be cancelled.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Student</th>
                            <th class="py-2 font-semibold">Assessment</th>
                            <th class="py-2 font-semibold">Change</th>
                            <th class="py-2 font-semibold">Reason</th>
                            <th class="py-2 font-semibold">Status</th>
                            <th class="py-2 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changeRequests as $req)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5">{{ $req->student->name }}</td>
                                <td class="py-2.5 text-neutral-500">{{ $req->assessment->name }}</td>
                                <td class="py-2.5">{{ $req->old_score !== null ? $req->old_score + 0 : '—' }} → <span class="font-semibold">{{ $req->new_score + 0 }}</span></td>
                                <td class="py-2.5 text-neutral-500">{{ $req->reason }}</td>
                                <td class="py-2.5">
                                    <x-badge :color="$req->status === 'Applied' ? 'green' : ($req->status === 'Rejected' || $req->status === 'Cancelled' ? 'pink' : 'yellow')">
                                        {{ str($req->status)->replace('_', ' ') }}
                                    </x-badge>
                                </td>
                                <td class="py-2.5 text-right">
                                    @if ($req->status === 'Pending')
                                        <form method="POST" action="{{ route('teacher.gradebook.change-requests.cancel', $req) }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @php $categoryPresets = ['Homework', 'Quiz', 'Test / Exam', 'Project', 'Classwork', 'Participation', 'Lab', 'Assignment', 'Extra Credit']; @endphp
    <x-card title="Categories & items" subtitle="Group work into weighted categories, then add the graded items (quizzes, tests, projects…) inside each. Weights should total 100%.">
        @if (! $term?->is_locked)
            <div class="space-y-4 mb-6">
                @forelse ($categories as $category)
                    <div class="border border-neutral-200 rounded-xl p-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">{{ $category->name }}</span>
                                <x-badge color="blue">{{ rtrim(rtrim(number_format($category->weight_pct, 2), '0'), '.') }}% weight</x-badge>
                                <span class="text-xs text-neutral-400">{{ $category->assessments->count() }} {{ Str::plural('item', $category->assessments->count()) }}</span>
                            </div>
                            <form method="POST" action="{{ route('teacher.gradebook.categories.destroy', $category) }}"
                                onsubmit="return confirm('Delete the “{{ $category->name }}” category and all its items and scores?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Delete category</button>
                            </form>
                        </div>

                        <div class="flex flex-wrap gap-2 mb-3">
                            @forelse ($category->assessments as $assessment)
                                <div x-data="{ edit: false }" class="border border-neutral-200 rounded-lg bg-neutral-50 px-3 py-2 text-sm">
                                    <div x-show="!edit" class="flex items-center gap-2">
                                        <span class="font-medium">{{ $assessment->name }}</span>
                                        <span class="text-xs text-neutral-400">/ {{ rtrim(rtrim(number_format($assessment->max_score, 2), '0'), '.') }} pts</span>
                                        <button type="button" @click="edit = true" class="text-xs font-semibold text-neutral-500 hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('teacher.gradebook.assessments.destroy', $assessment) }}"
                                            onsubmit="return confirm('Delete “{{ $assessment->name }}” and its scores?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                    <form x-show="edit" x-cloak method="POST" action="{{ route('teacher.gradebook.assessments.update', $assessment) }}" class="flex items-center gap-2">
                                        @csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $assessment->name }}" required maxlength="255"
                                            class="w-32 rounded border border-neutral-200 px-2 py-1 text-xs">
                                        <input type="number" step="0.01" min="1" name="max_score" value="{{ rtrim(rtrim(number_format($assessment->max_score, 2), '0'), '.') }}" required
                                            class="w-16 rounded border border-neutral-200 px-2 py-1 text-xs" title="Max points">
                                        <button type="submit" class="text-xs font-semibold text-[#1F573D] hover:underline">Save</button>
                                        <button type="button" @click="edit = false" class="text-xs text-neutral-400 hover:underline">Cancel</button>
                                    </form>
                                </div>
                            @empty
                                <span class="text-xs text-neutral-400 italic">No items yet — add the first one below.</span>
                            @endforelse
                        </div>

                        <form method="POST" action="{{ route('teacher.gradebook.assessments.store') }}" class="flex flex-wrap gap-2 items-end">
                            @csrf
                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                            <div>
                                <label class="block text-xs font-semibold text-neutral-500 mb-1">New item</label>
                                <input type="text" name="name" required placeholder="e.g. Quiz 1" class="w-40 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-neutral-500 mb-1">Max pts</label>
                                <input type="number" step="0.01" min="1" name="max_score" required value="100" class="w-20 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                            </div>
                            <button type="submit" class="border border-[#1F573D] text-[#1F573D] font-semibold rounded-lg px-3 py-2 text-sm hover:bg-[#1F573D] hover:text-white transition">+ Add item</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400 mb-4">No categories yet — add your first one below (e.g. Quiz 30%, Test 40%, Homework 30%).</p>
                @endforelse
            </div>

            <div class="border-t border-neutral-100 pt-4">
                <form method="POST" action="{{ route('teacher.gradebook.categories.store') }}" x-data="{ pick: 'Homework', custom: '' }" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <input type="hidden" name="section_id" value="{{ $section->id }}">
                    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                    <input type="hidden" name="term_id" value="{{ $term?->id }}">
                    {{-- The submitted name is the preset itself, or the typed value when "Other" is chosen. --}}
                    <input type="hidden" name="name" :value="pick === 'Other' ? custom : pick">
                    <div class="w-48">
                        <label class="block text-sm font-semibold mb-1">New category</label>
                        <select x-model="pick" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            @foreach ($categoryPresets as $preset)
                                <option value="{{ $preset }}">{{ $preset }}</option>
                            @endforeach
                            <option value="Other">Other (custom)…</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-40" x-show="pick === 'Other'" x-cloak>
                        <label class="block text-sm font-semibold mb-1">Custom name</label>
                        <input type="text" x-model="custom" :required="pick === 'Other'" placeholder="e.g. Group work" maxlength="255" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div class="w-32">
                        <label class="block text-sm font-semibold mb-1">Weight %</label>
                        <input type="number" step="0.01" name="weight_pct" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add category</button>
                </form>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($categories as $category)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-semibold">{{ $category->name }}</span>
                        <x-badge color="blue">{{ rtrim(rtrim(number_format($category->weight_pct, 2), '0'), '.') }}%</x-badge>
                        <span class="text-neutral-400 text-xs">{{ $category->assessments->pluck('name')->join(', ') ?: 'no items' }}</span>
                    </div>
                @endforeach
                <p class="text-xs text-amber-700">Term is locked — categories and items are read-only.</p>
            </div>
        @endif
    </x-card>

    @if ($allAssessments->isNotEmpty() && ! $term?->is_locked)
        <x-card title="Bulk import scores" subtitle="Pick an assessment, download its roster template, fill the score column, and upload — one assessment at a time.">
            @php $firstAssessment = $allAssessments->first(); @endphp
            <div x-data="{ aid: '{{ $firstAssessment->id }}' }" class="space-y-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-56">
                        <label class="block text-sm font-semibold mb-1">Assessment</label>
                        <select x-model="aid" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                            @foreach ($allAssessments as $assessment)
                                <option value="{{ $assessment->id }}">{{ $assessment->name }} (max {{ rtrim(rtrim(number_format($assessment->max_score, 2), '0'), '.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <a :href="`{{ url('teacher/gradebook/assessments') }}/${aid}/scores-template`"
                        class="text-sm font-semibold text-neutral-600 self-center hover:underline">Download template</a>
                </div>

                <form method="POST" :action="`{{ url('teacher/gradebook/assessments') }}/${aid}/scores-import`" enctype="multipart/form-data" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <div class="flex-1 min-w-56">
                        <label class="block text-sm font-semibold mb-1">Filled template (Excel / CSV)</label>
                        <input type="file" name="file" required accept=".xlsx,.xls,.csv" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Upload &amp; Import</button>
                </form>

                <p class="text-xs text-neutral-500">Columns: <code>student_id_number</code> and <code>score</code> (the <code>name</code> column is for your reference). Blank scores are skipped; out-of-range or non-enrolled rows are reported and never partially saved.</p>
            </div>

            @if (session('scoreImportResults'))
                @php $sr = session('scoreImportResults'); @endphp
                <div class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm font-semibold mb-1">Saved ({{ count($sr['updated']) }}) — {{ $sr['assessment'] }}</p>
                        @forelse ($sr['updated'] as $line)
                            <p class="text-xs text-green-700 py-0.5">{{ $line }}</p>
                        @empty
                            <p class="text-xs text-neutral-400">None.</p>
                        @endforelse
                    </div>
                    <div>
                        <p class="text-sm font-semibold mb-1">Skipped ({{ count($sr['skipped']) }})</p>
                        @forelse ($sr['skipped'] as $line)
                            <p class="text-xs text-yellow-700 py-0.5">{{ $line }}</p>
                        @empty
                            <p class="text-xs text-neutral-400">None.</p>
                        @endforelse
                    </div>
                    <div>
                        <p class="text-sm font-semibold mb-1">Errors ({{ count($sr['errors']) }})</p>
                        @forelse ($sr['errors'] as $line)
                            <p class="text-xs text-red-700 py-0.5">{{ $line }}</p>
                        @empty
                            <p class="text-xs text-neutral-400">None.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </x-card>
    @endif

    @php
        $gridCategories = $categories->filter(fn ($c) => $c->assessments->isNotEmpty())->values();
        $gridMeta = [
            'categories' => $gridCategories->map(fn ($c) => [
                'id' => $c->id,
                'weight' => (float) $c->weight_pct,
                'assessments' => $c->assessments->map(fn ($a) => ['id' => $a->id, 'max' => (float) $a->max_score])->values(),
            ])->values(),
            'students' => $roster->map(fn ($s) => ['id' => $s->id, 'dept' => $s->department_id])->values(),
            'bands' => $bandsByDept,
        ];
        $initialScores = [];
        foreach ($gridCategories as $gc) {
            foreach ($gc->assessments as $ga) {
                foreach ($roster as $gs) {
                    $g = $ga->grades->firstWhere('student_id', $gs->id);
                    $initialScores[$ga->id][$gs->id] = $g ? (float) $g->score : null;
                }
            }
        }
        $readonly = (bool) $term?->is_locked;
    @endphp

    <script>
        // Mirrors App\Services\GradeService exactly: category % = mean of (score/max*100) over graded
        // items; final = weighted mean of category %s, counting only categories that have a score.
        window.gradebookGrid = function (init) {
            return {
                meta: init.meta,
                scores: init.scores,
                num(aid, sid) {
                    const v = this.scores?.[aid]?.[sid];
                    if (v === '' || v === null || v === undefined) return null;
                    const n = parseFloat(v);
                    return isNaN(n) ? null : n;
                },
                catPct(cat, sid) {
                    let sum = 0, n = 0;
                    for (const a of cat.assessments) {
                        const v = this.num(a.id, sid);
                        if (v !== null && a.max > 0) { sum += (v / a.max) * 100; n++; }
                    }
                    return n ? sum / n : null;
                },
                finalPct(sid) {
                    let ws = 0, wu = 0;
                    for (const cat of this.meta.categories) {
                        const p = this.catPct(cat, sid);
                        if (p !== null) { ws += p * cat.weight; wu += cat.weight; }
                    }
                    return wu > 0 ? ws / wu : null;
                },
                letterFor(sid) {
                    const p = this.finalPct(sid);
                    if (p === null) return '';
                    const st = this.meta.students.find(s => s.id === sid);
                    const bands = (st && this.meta.bands[st.dept]) || [];
                    for (const b of bands) { if (p >= b.min) return b.letter; }
                    return '';
                },
                fmt(p) { return p === null ? '—' : (Math.round(p * 100) / 100) + '%'; },
            };
        };
    </script>

    <form method="POST" action="{{ route('teacher.gradebook.scores.store') }}"
        x-data="gradebookGrid({{ Illuminate\Support\Js::from(['meta' => $gridMeta, 'scores' => $initialScores]) }})">
        @csrf
        <input type="hidden" name="section_id" value="{{ $section->id }}">
        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
        <input type="hidden" name="term_id" value="{{ $term?->id }}">

        <x-card title="Enter scores" subtitle="Type a score for each item — subtotals and the weighted result update instantly. Enter points out of each item's maximum (not a percentage).">
            @if ($gridCategories->isEmpty())
                <p class="text-sm text-neutral-400 py-6 text-center">No graded items yet. Add categories and items above, then scores appear here.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="text-sm border-separate border-spacing-0">
                        <thead>
                            <tr class="text-neutral-500">
                                <th rowspan="2" class="sticky left-0 z-10 bg-white text-left py-2 pr-4 font-semibold border-b border-neutral-200 align-bottom">Student</th>
                                @foreach ($gridCategories as $category)
                                    <th colspan="{{ $category->assessments->count() + 1 }}" class="py-2 px-2 text-center font-semibold border-b border-l border-neutral-200 bg-neutral-50">
                                        {{ $category->name }}
                                        <span class="text-xs font-normal text-neutral-400">· {{ rtrim(rtrim(number_format($category->weight_pct, 2), '0'), '.') }}%</span>
                                    </th>
                                @endforeach
                                <th rowspan="2" class="py-2 px-3 text-right font-semibold border-b border-l border-neutral-200 align-bottom bg-emerald-50/50">Result</th>
                            </tr>
                            <tr class="text-neutral-400 text-xs">
                                @foreach ($gridCategories as $category)
                                    @foreach ($category->assessments as $assessment)
                                        <th class="py-1.5 px-2 font-medium border-b border-l border-neutral-200 text-center whitespace-nowrap">
                                            {{ $assessment->name }}<br><span class="text-neutral-300">/ {{ rtrim(rtrim(number_format($assessment->max_score, 2), '0'), '.') }}</span>
                                        </th>
                                    @endforeach
                                    <th class="py-1.5 px-2 font-semibold border-b border-l border-neutral-200 text-center bg-neutral-50">Subtotal</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roster as $student)
                                <tr class="hover:bg-neutral-50/50">
                                    <td class="sticky left-0 z-10 bg-white py-2 pr-4 font-semibold border-b border-neutral-100 whitespace-nowrap">{{ $student->name }}</td>
                                    @foreach ($gridCategories as $ci => $category)
                                        @foreach ($category->assessments as $assessment)
                                            <td class="py-1.5 px-2 border-b border-l border-neutral-100 text-center">
                                                <input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}"
                                                    name="scores[{{ $assessment->id }}][{{ $student->id }}]"
                                                    x-model="scores[{{ $assessment->id }}][{{ $student->id }}]"
                                                    placeholder="—" @if ($readonly) readonly @endif
                                                    class="w-16 rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs text-center focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] @if ($readonly) opacity-60 @endif">
                                            </td>
                                        @endforeach
                                        <td class="py-1.5 px-2 border-b border-l border-neutral-100 text-center font-medium text-neutral-600 bg-neutral-50/50"
                                            x-text="fmt(catPct(meta.categories[{{ $ci }}], {{ $student->id }}))"></td>
                                    @endforeach
                                    <td class="py-1.5 px-3 border-b border-l border-neutral-100 text-right font-bold bg-emerald-50/50 whitespace-nowrap">
                                        <span x-text="fmt(finalPct({{ $student->id }}))"></span>
                                        <span class="text-neutral-400 font-medium" x-text="letterFor({{ $student->id }}) ? '(' + letterFor({{ $student->id }}) + ')' : ''"></span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="99" class="py-4 text-neutral-400">No students enrolled.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @unless ($readonly)
                    <div class="flex items-center gap-3 mt-5">
                        <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Save gradebook</button>
                        <span class="text-xs text-neutral-400">Subtotals and results shown are live; they are recomputed and stored when you save. Students &amp; guardians see the breakdown read-only.</span>
                    </div>
                @endunless
            @endif
        </x-card>
    </form>

    @if ($isHomeroom)
        <x-card title="Report-card comment — homeroom" subtitle="Added by the homeroom teacher; appears on the student's term report card.">
            @foreach ($roster as $student)
                <form method="POST" action="{{ route('teacher.gradebook.comments.store') }}" class="flex gap-3 items-end mb-3">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <input type="hidden" name="term_id" value="{{ $term?->id }}">
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-neutral-500 mb-1">{{ $student->name }}</label>
                        <input type="text" name="comment" value="{{ $comments[$student->id]->comment ?? '' }}" placeholder="Consistent effort this term..."
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Save comment</button>
                </form>
            @endforeach
        </x-card>
    @endif
</x-app-layout>
