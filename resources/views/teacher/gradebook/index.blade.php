@php $allAssessments = $categories->flatMap->assessments; @endphp
<x-app-layout title="Category Gradebook" subtitle="Set category weights, create assessments, enter scores, and add report-card comments — assigned classes only." badge="Teacher · Assigned classes only" role="teacher">
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

    <x-card title="Grade categories and weights" subtitle="Weights are visible to students and guardians read-only.">
        <div class="flex flex-wrap gap-3 mb-4">
            @forelse ($categories as $category)
                <div class="flex items-center gap-2 border border-neutral-200 rounded-xl px-3 py-2 text-sm">
                    <span class="font-semibold">{{ $category->name }}</span>
                    <x-badge color="blue">{{ rtrim(rtrim(number_format($category->weight_pct, 2), '0'), '.') }}%</x-badge>
                    <form method="POST" action="{{ route('teacher.gradebook.categories.destroy', $category) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Del</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No categories yet — add one below.</p>
            @endforelse
        </div>

        @php $categoryPresets = ['Homework', 'Quiz', 'Test / Exam', 'Project', 'Classwork', 'Participation', 'Lab', 'Assignment', 'Extra Credit']; @endphp
        <form method="POST" action="{{ route('teacher.gradebook.categories.store') }}" x-data="{ pick: 'Homework', custom: '' }" class="flex flex-wrap gap-3 items-end">
            @csrf
            <input type="hidden" name="section_id" value="{{ $section->id }}">
            <input type="hidden" name="subject_id" value="{{ $subject->id }}">
            <input type="hidden" name="term_id" value="{{ $term?->id }}">
            {{-- The submitted name is the preset itself, or the typed value when "Other" is chosen. --}}
            <input type="hidden" name="name" :value="pick === 'Other' ? custom : pick">
            <div class="w-48">
                <label class="block text-sm font-semibold mb-1">Category type</label>
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
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add Category</button>
        </form>
    </x-card>

    <x-card title="Add assessment">
        <form method="POST" action="{{ route('teacher.gradebook.assessments.store') }}" class="flex gap-3 items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-semibold mb-1">Category</label>
                <select name="category_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-sm font-semibold mb-1">Assessment name</label>
                <input type="text" name="name" required placeholder="Quiz 1" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div class="w-32">
                <label class="block text-sm font-semibold mb-1">Max score</label>
                <input type="number" step="0.01" name="max_score" required value="100" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add Assessment</button>
        </form>
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

    <form method="POST" action="{{ route('teacher.gradebook.scores.store') }}">
        @csrf
        <input type="hidden" name="section_id" value="{{ $section->id }}">
        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
        <input type="hidden" name="term_id" value="{{ $term?->id }}">

        <x-card title="Enter scores" subtitle="Each row calculates the weighted result from category scores.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Student</th>
                            @foreach ($allAssessments as $assessment)
                                <th class="py-2 font-semibold">{{ $assessment->name }}</th>
                            @endforeach
                            <th class="py-2 font-semibold">Weighted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roster as $student)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5 font-semibold">{{ $student->name }}</td>
                                @foreach ($allAssessments as $assessment)
                                    @php $grade = $assessment->grades->firstWhere('student_id', $student->id); @endphp
                                    <td class="py-2.5">
                                        <input type="number" step="0.01" name="scores[{{ $assessment->id }}][{{ $student->id }}]"
                                            value="{{ $grade?->score }}" placeholder="—"
                                            class="w-16 rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                    </td>
                                @endforeach
                                <td class="py-2.5 font-semibold">
                                    {{ $results[$student->id]['pct'] !== null ? $results[$student->id]['pct'].'%' : '—' }}
                                    @if ($results[$student->id]['letter'])
                                        <span class="text-neutral-400">({{ $results[$student->id]['letter'] }})</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="py-4 text-neutral-400">No students enrolled.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <button type="submit" class="mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Save Gradebook</button>
            <x-badge color="yellow">Students and guardians see the category breakdown read-only.</x-badge>
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
