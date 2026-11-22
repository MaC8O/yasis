@php
    $u = auth()->user();
    $badge = match (true) {
        $u->hasRole('vp_academic') => 'VP', $u->hasRole('principal') => 'Principal', $u->hasRole('admin') => 'Admin', default => 'Teacher',
    };
    $subtitle = match (true) {
        $canSubmit && ! $canViewAll => 'Upload a lesson plan for a class you teach. The Vice Principal reviews it.',
        $u->hasRole('vp_academic') => 'Every lesson plan. Review the ones waiting in the review queue.',
        default => 'Every lesson plan and its review history. Read only.',
    };
    $input = 'w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm';
@endphp
<x-app-layout title="Lesson Plans" :subtitle="$subtitle" :badge="$badge">
    @if ($canViewAll)
        @include('lesson-plans.partials.tabs', ['active' => 'all', 'queueCount' => $queueCount])
    @endif

    @if ($canSubmit)
        <x-card title="Upload a lesson plan" subtitle="PDF, Word (.docx) or PowerPoint (.pptx), up to 10 MB. You can upload a new version later; earlier versions are kept.">
            @if (! $year)
                <p class="text-sm text-neutral-500">There is no active school year, so plans cannot be uploaded yet.</p>
            @elseif ($assignments->isEmpty())
                <p class="text-sm text-neutral-500">You have no classes assigned this school year, so there is nothing to upload a plan for.</p>
            @else
                <form method="POST" action="{{ route('lesson-plans.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="teaching_assignment_id">Class</label>
                        <select id="teaching_assignment_id" name="teaching_assignment_id" required class="{{ $input }}">
                            <option value="">Choose a class…</option>
                            @foreach ($assignments as $a)
                                <option value="{{ $a->id }}" @selected((int) old('teaching_assignment_id') === $a->id)>{{ $a->subject?->name }} · {{ $a->section?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="term_id">Term</label>
                        <select id="term_id" name="term_id" required class="{{ $input }}">
                            <option value="">Choose a term…</option>
                            @foreach ($terms as $term)
                                <option value="{{ $term->id }}" @selected((int) old('term_id') === $term->id)>{{ $term->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold mb-1" for="title">Title</label>
                        <input id="title" name="title" type="text" required maxlength="150" value="{{ old('title') }}" class="{{ $input }}" placeholder="e.g. Week 3: Persuasive writing">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold mb-1" for="note">Note <span class="font-normal text-neutral-500">(optional)</span></label>
                        <textarea id="note" name="note" rows="2" maxlength="1000" class="{{ $input }}">{{ old('note') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold mb-1" for="file">File</label>
                        <input id="file" name="file" type="file" required accept=".pdf,.docx,.pptx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation"
                               class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-900 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark">Upload lesson plan</button>
                    </div>
                </form>
            @endif
        </x-card>

        <x-card title="My lesson plans" subtitle="Grouped by subject. Plans for classes you no longer teach stay here, read only.">
            @forelse ($myPlansBySubject as $subject => $plans)
                <section class="{{ $loop->first ? '' : 'mt-6' }}">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-500">{{ $subject }}</h3>
                    <ul class="mt-2 divide-y divide-neutral-100 border border-neutral-200 rounded-xl">
                        @foreach ($plans as $plan)
                            <li>
                                <a href="{{ route('lesson-plans.show', $plan) }}" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 px-4 py-3 hover:bg-neutral-50">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-brand break-words">{{ $plan->title }}</p>
                                        <p class="text-xs text-neutral-500">
                                            {{ $plan->section?->name }} · {{ $plan->term?->name }}
                                            @if ($plan->section?->academicYear && ! $plan->section->academicYear->is_active) · {{ $plan->section->academicYear->year_label }} @endif
                                            · Version {{ $plan->current_version }} · Updated {{ $plan->updated_at->format('j M Y') }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @unless ($changeable->has($plan->id))
                                            <span class="text-xs font-semibold text-neutral-500">Read only</span>
                                        @endunless
                                        <x-badge :color="$plan->statusColor()">{{ $plan->statusLabel() }}</x-badge>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="text-sm text-neutral-400">You have not uploaded any lesson plans yet.</p>
            @endforelse
        </x-card>
    @endif

    @if ($canViewAll)
        <x-card title="All lesson plans">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-department">Department</label>
                    <select id="filter-department" name="department" class="{{ $input }}">
                        <option value="">All departments</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected((int) ($filters['department'] ?? 0) === $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-section">Section</label>
                    <select id="filter-section" name="section" class="{{ $input }}">
                        <option value="">All sections</option>
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}" @selected((int) ($filters['section'] ?? 0) === $s->id)>{{ $s->name }}@if ($s->academicYear && ! $s->academicYear->is_active) ({{ $s->academicYear->year_label }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-status">Status</label>
                    <select id="filter-status" name="status" class="{{ $input }}">
                        <option value="">Any status</option>
                        @foreach (\App\Models\LessonPlan::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1" for="filter-term">Term</label>
                    <select id="filter-term" name="term" class="{{ $input }}">
                        <option value="">Any term</option>
                        @foreach ($filterTerms as $t)
                            <option value="{{ $t->id }}" @selected((int) ($filters['term'] ?? 0) === $t->id)>{{ $t->name }} ({{ $t->academicYear?->year_label }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-700">Filter</button>
                    <a href="{{ route('lesson-plans.index') }}" class="px-3 py-2.5 text-sm font-semibold text-neutral-500">Clear</a>
                </div>
            </form>

            @if ($allPlans->isEmpty())
                <p class="text-sm text-neutral-400">No lesson plans match.</p>
            @else
                @include('lesson-plans.partials.plan-list', ['plans' => $allPlans])
                <div class="mt-4 text-xs">{{ $allPlans->links() }}</div>
            @endif
        </x-card>
    @endif
</x-app-layout>
