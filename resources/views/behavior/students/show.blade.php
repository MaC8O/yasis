@php
    $user = auth()->user();
    $activeYearId = $summary['year']?->id;
    $stageLabels = \App\Models\BehaviorFlag::LABELS;
    $readOnly = ! $isVp && ! $canReport;
@endphp
<x-app-layout :title="$student->name" :subtitle="$student->student_id_number.' · '.($student->department?->name ?? '').' · Behavior record'" :badge="$isVp ? 'VP' : ($readOnly ? 'Read only' : 'Teacher')">
    @include('behavior.partials.summary', ['summary' => $summary])

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('behavior.index') }}" class="text-sm font-semibold text-neutral-600 hover:underline">← Back to Behavior</a>
        @if ($canReport)
            <a href="{{ route('behavior.reports.create', $student) }}" class="ml-auto bg-brand text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-brand-dark">Report incident</a>
        @endif
    </div>

    @if ($isVp && $summary['year'])
        <x-card title="New report with deduction" subtitle="For an incident you handled directly. The report and the deduction are recorded together.">
            <details @if ($errors->any() && old('points')) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-brand">Open the form</summary>
                <form method="POST" action="{{ route('behavior.reports.vp-store', $student) }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @include('behavior.partials.report-fields', ['report' => null])
                    <div>
                        <label class="block text-sm font-semibold mb-1" for="vp-points">Points to deduct</label>
                        <input id="vp-points" type="number" name="points" min="1" max="100" step="1" value="{{ old('points') }}" required class="w-32 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        <p class="text-xs text-neutral-500 mt-1">If this is more than the current score ({{ $summary['score'] }}), the score stops at 0 and the ledger records the points actually applied.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold mb-1" for="vp-reason">Reason for the deduction (required, shown to the family)</label>
                        <textarea id="vp-reason" name="reason" rows="2" required minlength="5" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">{{ old('reason') }}</textarea>
                    </div>
                    <div class="sm:col-span-2"><button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark">Record and deduct</button></div>
                </form>
            </details>
        </x-card>
    @endif

    <x-card title="Reports" subtitle="Every report stays here, including withdrawn ones and earlier versions of edited ones.">
        <div class="space-y-3">
            @forelse ($reports as $report)
                @include('behavior.partials.report-card', [
                    'report' => $report, 'isVp' => $isVp,
                    'isAuthor' => (int) $report->reported_by === (int) $user->id,
                ])
                @if ((int) $report->academic_year_id !== (int) $activeYearId)
                    <p class="-mt-2 text-xs text-neutral-400">School year {{ $report->academicYear?->year_label }}</p>
                @endif
            @empty
                <p class="text-sm text-neutral-400">No behavior reports.</p>
            @endforelse
        </div>
    </x-card>

    <x-card title="Score history" subtitle="Every deduction and restoration, newest first.">
        @if ($entries->isEmpty())
            <p class="text-sm text-neutral-400">No points have been deducted.</p>
        @else
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Date</th>
                            <th class="py-2 font-semibold">Entry</th>
                            <th class="py-2 font-semibold text-right">Points</th>
                            <th class="py-2 font-semibold text-right">Score</th>
                            <th class="py-2 font-semibold pl-6">Reason</th>
                            <th class="py-2 font-semibold">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr class="border-b border-neutral-100 last:border-0 align-top">
                                <td class="py-2.5 whitespace-nowrap">{{ $entry->created_at->format('j M Y') }}</td>
                                <td class="py-2.5">{{ $entry->type }}{{ $entry->report ? ' · '.$entry->report->category?->name : '' }}</td>
                                <td class="py-2.5 text-right tabular-nums font-semibold {{ $entry->isDeduction() ? 'text-danger' : 'text-success' }}">
                                    {{ $entry->isDeduction() ? '−' : '+' }}{{ $entry->points_applied }}
                                    @if ($entry->points_applied < $entry->points_requested)<span class="block text-[11px] font-normal text-neutral-500">{{ $entry->points_requested }} entered</span>@endif
                                </td>
                                <td class="py-2.5 text-right tabular-nums">{{ $entry->score_before }} → {{ $entry->score_after }}</td>
                                <td class="py-2.5 pl-6 text-neutral-700">{{ $entry->reason }}</td>
                                <td class="py-2.5 text-neutral-500">{{ $entry->actor?->user?->name }}</td>
                            </tr>
                            @if ($isVp && $entry->isDeduction() && (int) $entry->academic_year_id === (int) $activeYearId && ($left = $entry->restorablePoints()) > 0)
                                <tr class="border-b border-neutral-100"><td colspan="6" class="pb-3">@include('behavior.partials.restore-form', ['entry' => $entry, 'left' => $left])</td></tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="md:hidden space-y-3">
                @foreach ($entries as $entry)
                    <li class="border border-neutral-200 rounded-xl p-4 text-sm">
                        <div class="flex justify-between gap-2">
                            <p class="font-semibold">{{ $entry->type }}</p>
                            <p class="font-semibold tabular-nums {{ $entry->isDeduction() ? 'text-danger' : 'text-success' }}">{{ $entry->isDeduction() ? '−' : '+' }}{{ $entry->points_applied }}</p>
                        </div>
                        <p class="text-xs text-neutral-500">{{ $entry->created_at->format('j M Y') }} · Score {{ $entry->score_before }} → {{ $entry->score_after }} · {{ $entry->actor?->user?->name }}</p>
                        @if ($entry->points_applied < $entry->points_requested)<p class="text-xs text-neutral-500">{{ $entry->points_requested }} entered; capped at 0.</p>@endif
                        <p class="mt-1 text-neutral-700">{{ $entry->reason }}</p>
                        @if ($isVp && $entry->isDeduction() && (int) $entry->academic_year_id === (int) $activeYearId && ($left = $entry->restorablePoints()) > 0)
                            <div class="mt-2">@include('behavior.partials.restore-form', ['entry' => $entry, 'left' => $left])</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card title="Warning stages" subtitle="A flag opens when the score reaches a stage’s threshold and closes when it rises above it again. Closed flags stay here.">
        @if ($flags->isEmpty())
            <p class="text-sm text-neutral-400">This student has never reached a warning stage.</p>
        @else
            <ul class="divide-y divide-neutral-100 text-sm">
                @foreach ($flags as $flag)
                    <li class="py-2.5 flex flex-wrap items-center justify-between gap-2">
                        <span><x-badge :color="$flag->isOpen() ? 'pink' : 'neutral'">{{ $stageLabels[$flag->stage] }}</x-badge>
                            <span class="ml-2 text-neutral-600">reached {{ $flag->opened_at->format('j M Y') }} at score {{ $flag->score_at_open }} (threshold {{ $flag->threshold_at_open }})</span></span>
                        <span class="text-xs {{ $flag->isOpen() ? 'font-semibold text-danger' : 'text-neutral-500' }}">{{ $flag->isOpen() ? 'Open' : 'Closed '.$flag->closed_at->format('j M Y').' — '.$flag->close_reason }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($cases->isNotEmpty())
        <x-card title="Expulsion reviews">
            <ul class="divide-y divide-neutral-100 text-sm">
                @foreach ($cases as $case)
                    <li class="py-2.5 flex flex-wrap items-center justify-between gap-2">
                        @if ($user->hasAnyRole(['vp_academic', 'principal', 'admin', 'registrar']))
                            <a href="{{ route('behavior.cases.show', $case) }}" class="font-semibold text-brand hover:underline">Case opened {{ $case->created_at->format('j M Y') }}</a>
                        @else
                            <span class="font-semibold">Case opened {{ $case->created_at->format('j M Y') }}</span>
                        @endif
                        <x-badge :color="$case->isOpen() ? 'yellow' : 'neutral'">{{ $case->isOpen() ? 'Open · round '.$case->round : 'Decided: '.$case->outcome }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-app-layout>
