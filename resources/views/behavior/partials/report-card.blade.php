{{--
    One behavior report, as everyone sees it: nothing is hidden from the student or guardian.
    Withdrawn reports show the teacher's reason; edited reports show the date and earlier versions.
    $report; optional $isVp (review forms), $isAuthor (edit/withdraw while Submitted), $showStudent.
--}}
@php
    $isVp = $isVp ?? false;
    $isAuthor = $isAuthor ?? false;
    $showStudent = $showStudent ?? false;
    $deduction = $report->deduction;
    $restored = $deduction ? (int) $deduction->restorations->sum('points_applied') : 0;
    $reporterLabel = $report->source === 'VP' ? 'Vice Principal' : ($report->reporter?->user?->name ?? 'Teacher');
@endphp
<article class="border border-neutral-200 rounded-xl p-4 {{ $report->status === 'Voided' ? 'bg-neutral-50' : 'bg-white' }}">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            @if ($showStudent)
                <p class="text-xs font-semibold text-neutral-500">{{ $report->student?->name }} · {{ $report->student?->student_id_number }}</p>
            @endif
            <p class="font-semibold text-ink {{ $report->status === 'Voided' ? 'line-through decoration-neutral-400' : '' }}">{{ $report->category?->name }}</p>
            <p class="text-xs text-neutral-500 mt-0.5">
                {{ $report->incident_date->format('j M Y') }} · Reported by {{ $reporterLabel }}
                @if ($report->is_office_referral)
                    · <span class="font-semibold text-tint-blue-ink">Sent to the office</span>
                @endif
            </p>
        </div>
        <x-badge :color="$report->statusColor()" class="shrink-0">{{ $report->statusLabel() }}</x-badge>
    </div>

    <p class="text-sm text-neutral-700 mt-3 whitespace-pre-line">{{ $report->description }}</p>

    @if ($report->edited_at)
        <details class="mt-3 text-xs">
            <summary class="cursor-pointer font-semibold text-neutral-600">Edited on {{ $report->edited_at->format('j M Y, H:i') }} · show earlier {{ Str::plural('version', $report->versions->count()) }}</summary>
            <ol class="mt-2 space-y-2 border-l-2 border-neutral-200 pl-3">
                @foreach ($report->versions as $version)
                    <li>
                        <p class="text-neutral-500">Version {{ $version->version }} · written {{ $version->recorded_at->format('j M Y, H:i') }}</p>
                        <p class="font-semibold text-neutral-700">{{ $version->category?->name }} · {{ $version->incident_date->format('j M Y') }}{{ $version->is_office_referral ? ' · Sent to the office' : '' }}</p>
                        <p class="text-neutral-600 whitespace-pre-line">{{ $version->description }}</p>
                    </li>
                @endforeach
            </ol>
        </details>
    @endif

    @if ($report->status === 'Voided')
        <p class="mt-3 text-sm rounded-lg bg-neutral-100 px-3 py-2"><span class="font-semibold">Withdrawn by the teacher</span> on {{ $report->voided_at->format('j M Y') }}. Reason: {{ $report->void_reason }}</p>
    @elseif ($report->status === 'Dismissed')
        <p class="mt-3 text-sm rounded-lg bg-tint-blue px-3 py-2"><span class="font-semibold">Reviewed by the Vice Principal — no deduction</span> on {{ $report->reviewed_at->format('j M Y') }}. Reason: {{ $report->dismiss_reason }}</p>
    @elseif ($report->status === 'Deducted' && $deduction)
        <div class="mt-3 text-sm rounded-lg bg-tint-pink px-3 py-2">
            <p><span class="font-semibold">−{{ $deduction->points_applied }} {{ Str::plural('point', $deduction->points_applied) }}</span>
                @if ($deduction->points_applied < $deduction->points_requested)
                    <span class="text-xs">({{ $deduction->points_requested }} entered; capped because the score cannot go below 0)</span>
                @endif
                · {{ $deduction->created_at->format('j M Y') }}</p>
            <p>Reason: {{ $deduction->reason }}</p>
            @foreach ($deduction->restorations as $restoration)
                <p class="mt-1 text-tint-green-ink"><span class="font-semibold">+{{ $restoration->points_applied }} restored</span> on {{ $restoration->created_at->format('j M Y') }}. Reason: {{ $restoration->reason }}</p>
            @endforeach
        </div>
    @elseif ($report->status === 'Submitted')
        <p class="mt-3 text-xs text-neutral-500">Awaiting review by the Vice Principal. This report does not change the behavior score.</p>
    @endif

    @if ($isAuthor && $report->status === 'Submitted' && $report->source === 'Teacher')
        <div class="mt-3 flex flex-wrap gap-3 items-start">
            <a href="{{ route('behavior.reports.edit', $report) }}" class="text-sm font-semibold text-brand hover:underline">Edit</a>
            <details class="text-sm">
                <summary class="cursor-pointer font-semibold text-danger">Withdraw…</summary>
                <form method="POST" action="{{ route('behavior.reports.void', $report) }}" class="mt-2 space-y-2"
                      data-confirm="Withdraw this report? It stays visible to the student and guardians as “Withdrawn” with your reason."
                      onsubmit="return confirm(this.dataset.confirm);">
                    @csrf
                    <label class="block text-xs font-semibold text-neutral-600" for="void-{{ $report->id }}">Reason (required, shown to the family)</label>
                    <textarea id="void-{{ $report->id }}" name="reason" required minlength="5" rows="2" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="bg-danger text-white font-semibold rounded-lg px-4 py-2 text-sm">Withdraw report</button>
                </form>
            </details>
        </div>
    @endif

    @if ($isVp && $report->status === 'Submitted')
        <div class="mt-4 pt-3 border-t border-neutral-100 grid grid-cols-1 md:grid-cols-2 gap-4">
            <form method="POST" action="{{ route('behavior.reports.deduct', $report) }}" class="space-y-2">
                @csrf
                <p class="text-xs font-bold uppercase tracking-wider text-neutral-500">Deduct points</p>
                <label class="block text-xs font-semibold text-neutral-600" for="pts-{{ $report->id }}">Points</label>
                <input id="pts-{{ $report->id }}" type="number" name="points" min="1" max="100" step="1" required class="w-28 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                <label class="block text-xs font-semibold text-neutral-600" for="dr-{{ $report->id }}">Reason (required, shown to the family)</label>
                <textarea id="dr-{{ $report->id }}" name="reason" required minlength="5" rows="2" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm"></textarea>
                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark">Deduct</button>
            </form>
            <form method="POST" action="{{ route('behavior.reports.dismiss', $report) }}" class="space-y-2">
                @csrf
                <p class="text-xs font-bold uppercase tracking-wider text-neutral-500">Dismiss — no deduction</p>
                <label class="block text-xs font-semibold text-neutral-600" for="dis-{{ $report->id }}">Reason (required, shown to the family)</label>
                <textarea id="dis-{{ $report->id }}" name="reason" required minlength="5" rows="2" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm"></textarea>
                <button type="submit" class="w-full sm:w-auto bg-white border border-neutral-300 text-ink font-semibold rounded-lg px-4 py-2 text-sm hover:border-brand">Dismiss</button>
            </form>
        </div>
    @endif
</article>
