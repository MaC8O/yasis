@php
    $u = auth()->user();
    $badge = match (true) {
        $u->hasRole('vp_academic') => 'VP', $u->hasRole('principal') => 'Principal', $u->hasRole('admin') => 'Admin', default => 'Teacher',
    };
    $input = 'w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm';
    $limitMb = \App\Models\LessonPlan::MAX_TOTAL_BYTES / 1048576;
    $usedMb = number_format($usedBytes / 1048576, 1);
@endphp
<x-app-layout title="Lesson plan" :subtitle="$plan->subject?->name.' · '.$plan->section?->name.' · '.$plan->term?->name" :badge="$badge">
    <div class="flex flex-wrap gap-3 text-sm">
        <a href="{{ route('lesson-plans.index') }}" class="font-semibold text-neutral-600 hover:underline">← Back to Lesson Plans</a>
        @if ($u->can(\App\Support\LessonPlanPermissions::VIEW_ALL))
            <a href="{{ route('lesson-plans.queue') }}" class="font-semibold text-brand hover:underline">Review queue →</a>
        @endif
    </div>

    <x-card>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink break-words">{{ $plan->title }}</h2>
                <p class="text-sm text-neutral-500">{{ $plan->teacher?->user?->name }}</p>
            </div>
            <x-badge :color="$plan->statusColor()" class="self-start shrink-0">{{ $plan->statusLabel() }}</x-badge>
        </div>
        <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div><dt class="text-xs text-neutral-500">Subject</dt><dd class="font-medium">{{ $plan->subject?->name }}</dd></div>
            <div><dt class="text-xs text-neutral-500">Section</dt><dd class="font-medium">{{ $plan->section?->name }}
                @if ($plan->section?->academicYear && ! $plan->section->academicYear->is_active)<span class="block text-xs text-neutral-400">{{ $plan->section->academicYear->year_label }}</span>@endif</dd></div>
            <div><dt class="text-xs text-neutral-500">Term</dt><dd class="font-medium">{{ $plan->term?->name }}</dd></div>
            <div><dt class="text-xs text-neutral-500">Current version</dt><dd class="font-medium tabular-nums">{{ $plan->current_version }}</dd></div>
        </dl>
        @if ($plan->note)
            <div class="mt-4">
                <p class="text-xs text-neutral-500">Note</p>
                <p class="text-sm text-neutral-800 whitespace-pre-line break-words">{{ $plan->note }}</p>
            </div>
        @endif
        @if ($currentVersion)
            <a href="{{ route('lesson-plans.versions.download', [$plan, $currentVersion]) }}"
               class="mt-5 inline-flex items-center gap-2 bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-700">
                <x-icon name="document" class="w-4 h-4" /> Download version {{ $currentVersion->version }} ({{ $currentVersion->typeLabel() }}, {{ $currentVersion->humanSize() }})
            </a>
        @endif
        @if ($isOwner && ! $canChange)
            <p class="mt-4 text-sm text-neutral-500">Read only: you no longer teach {{ $plan->subject?->name }} in {{ $plan->section?->name }} this school year.</p>
        @endif
    </x-card>

    @if ($canReview && $currentVersion)
        <x-card title="Review version {{ $currentVersion->version }}" subtitle="The teacher is notified in the portal. “Needs changes” also e-mails them and needs a comment.">
            <form method="POST" action="{{ route('lesson-plans.reviews.store', $plan) }}" class="space-y-4" x-data="{ decision: @js(old('decision', '')) }">
                @csrf
                <input type="hidden" name="version_id" value="{{ $currentVersion->id }}">
                <fieldset>
                    <legend class="text-sm font-semibold mb-2">Decision</legend>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <label class="inline-flex items-center gap-2 text-sm min-h-11 border border-neutral-200 rounded-lg px-4">
                            <input type="radio" name="decision" value="Approved" x-model="decision" required class="w-5 h-5"> Approve
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm min-h-11 border border-neutral-200 rounded-lg px-4">
                            <input type="radio" name="decision" value="NeedsChanges" x-model="decision" class="w-5 h-5"> Needs changes
                        </label>
                    </div>
                </fieldset>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="comment">Comment <span class="font-normal text-neutral-500" x-text="decision === 'NeedsChanges' ? '(required)' : '(optional)'">(optional)</span></label>
                    <textarea id="comment" name="comment" rows="3" maxlength="1000" class="{{ $input }}" x-bind:required="decision === 'NeedsChanges'">{{ old('comment') }}</textarea>
                </div>
                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark">Save review</button>
            </form>
        </x-card>
    @endif

    @if ($canChange)
        <x-card title="Upload a new version"
                :subtitle="'The plan goes back to “Awaiting review”. Earlier versions are kept. All versions together: '.$usedMb.' of '.$limitMb.' MB used.'">
            @if ($atLimit)
                <p class="text-sm text-neutral-600">This plan has reached its {{ $limitMb }} MB limit for all versions together. Upload the new file as a new lesson plan instead.</p>
            @else
                <form method="POST" action="{{ route('lesson-plans.versions.store', $plan) }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                    @csrf
                    <div class="flex-1 min-w-0">
                        <label class="block text-sm font-semibold mb-1" for="version-file">File <span class="font-normal text-neutral-500">(PDF, .docx or .pptx, up to 10 MB)</span></label>
                        <input id="version-file" name="file" type="file" required accept=".pdf,.docx,.pptx"
                               class="block w-full text-sm text-neutral-700 file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-900 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white">
                    </div>
                    <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark">Upload version {{ $plan->current_version + 1 }}</button>
                </form>
            @endif
        </x-card>
    @endif

    <x-card title="Review history" subtitle="The Vice Principal’s decisions, newest first.">
        @forelse ($plan->reviews as $review)
            <div class="{{ $loop->first ? '' : 'mt-3' }} border border-neutral-200 rounded-xl p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-badge :color="$review->decision === 'Approved' ? 'green' : 'pink'">{{ $review->decisionLabel() }}</x-badge>
                    <span class="text-xs text-neutral-500">Version {{ $review->version?->version }} · {{ $review->reviewer?->user?->name }} · {{ $review->created_at->format('j M Y, H:i') }}</span>
                </div>
                @if ($review->comment)
                    <p class="mt-2 text-sm text-neutral-800 whitespace-pre-line break-words">{{ $review->comment }}</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-neutral-400">Not reviewed yet.</p>
        @endforelse
    </x-card>

    <x-card title="Versions" subtitle="Every uploaded file is kept.">
        <ul class="divide-y divide-neutral-100">
            @foreach ($plan->versions as $version)
                <li class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-sm">
                    <div class="min-w-0">
                        <p class="font-semibold">Version {{ $version->version }}
                            @if ($version->version === $plan->current_version)<span class="text-xs font-semibold text-success">· current</span>@endif</p>
                        <p class="text-xs text-neutral-500 break-all">{{ $version->original_name }} · {{ $version->typeLabel() }} · {{ $version->humanSize() }} · {{ $version->created_at->format('j M Y, H:i') }}</p>
                    </div>
                    <a href="{{ route('lesson-plans.versions.download', [$plan, $version]) }}" class="font-semibold text-brand hover:underline shrink-0">Download</a>
                </li>
            @endforeach
        </ul>
    </x-card>

    @if ($canChange)
        <x-card title="Title and note">
            <form method="POST" action="{{ route('lesson-plans.update', $plan) }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-semibold mb-1" for="title">Title</label>
                    <input id="title" name="title" type="text" required maxlength="150" value="{{ old('title', $plan->title) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="note">Note <span class="font-normal text-neutral-500">(optional)</span></label>
                    <textarea id="note" name="note" rows="2" maxlength="1000" class="{{ $input }}">{{ old('note', $plan->note) }}</textarea>
                </div>
                <button type="submit" class="w-full sm:w-auto bg-neutral-900 text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-neutral-700">Save</button>
            </form>
        </x-card>
    @endif

    @if ($canChange)
        <x-card title="Delete plan">
            @if ($canDelete)
                <form method="POST" action="{{ route('lesson-plans.destroy', $plan) }}" onsubmit="return confirm('Delete this lesson plan and all its files? This cannot be undone.');"
                      class="flex flex-col sm:flex-row sm:items-center gap-3">
                    @csrf @method('DELETE')
                    <p class="text-sm text-neutral-600 flex-1">Possible only until the Vice Principal reviews it. Removes the plan and every version’s file.</p>
                    <button type="submit" class="w-full sm:w-auto border border-danger-line text-danger font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-danger-soft">Delete plan</button>
                </form>
            @else
                <p class="text-sm text-neutral-600">This plan has been reviewed, so it can’t be deleted. Upload a new version instead.</p>
            @endif
        </x-card>
    @endif
</x-app-layout>
