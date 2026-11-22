@php
    $student = $case->student;
    $exit = $case->exitRequest;
    $myTurn = $case->isOpen() && $role !== null && $case->awaiting === $role;
    $aboveThreshold = $score !== null && $score > $thresholds['ExpulsionReview'];
@endphp
<x-app-layout title="Expulsion review" :subtitle="$student->name.' · '.$student->student_id_number" :badge="$role ?? 'Read only'">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-tile label="Current score" :color="$aboveThreshold ? 'green' : 'pink'">{{ $score ?? '—' }}</x-stat-tile>
        <x-stat-tile label="Expulsion review threshold" color="blue">{{ $thresholds['ExpulsionReview'] }}</x-stat-tile>
        <x-stat-tile label="Status" :color="$case->isOpen() ? 'yellow' : 'teal'">
            <span class="text-lg">{{ $case->isOpen() ? 'Open · round '.$case->round.' of '.\App\Models\BehaviorCase::MAX_ROUNDS : 'Decided: '.$case->outcome }}</span>
        </x-stat-tile>
    </div>

    @if ($case->isOpen() && $aboveThreshold)
        <div role="status" class="flex items-start gap-3 bg-info-soft border border-info-line text-info text-sm rounded-xl px-4 py-3">
            <x-icon name="alert" class="w-5 h-5 mt-0.5 shrink-0" />
            <p>Points have been restored since this case opened: the score ({{ $score }}) is now above the Expulsion review threshold ({{ $thresholds['ExpulsionReview'] }}). The case stays open until both the VP and the Principal decide.</p>
        </div>
    @endif

    <div class="flex flex-wrap gap-3 text-sm">
        <a href="{{ route('behavior.cases.index') }}" class="font-semibold text-neutral-600 hover:underline">← All expulsion reviews</a>
        <a href="{{ route('behavior.students.show', $student) }}" class="font-semibold text-brand hover:underline">Open {{ $student->name }}’s behavior record →</a>
    </div>

    <x-card title="Decisions" subtitle="The VP decides first, then the Principal. If they differ, the case goes back to the VP. After {{ \App\Models\BehaviorCase::MAX_ROUNDS }} rounds without agreement it closes as Monitor.">
        @forelse ($case->decisions->groupBy('round') as $round => $decisions)
            <div class="{{ $loop->first ? '' : 'mt-4 pt-4 border-t border-neutral-100' }}">
                <p class="text-xs font-bold uppercase tracking-wider text-neutral-500">Round {{ $round }}</p>
                @foreach ($decisions as $d)
                    <div class="mt-2 text-sm">
                        <p><span class="font-semibold">{{ $d->role === 'VP' ? 'Vice Principal' : 'Principal' }}</span> ({{ $d->decider?->user?->name }}) chose
                            <x-badge :color="$d->decision === 'Expel' ? 'pink' : ($d->decision === 'Monitor' ? 'yellow' : 'green')">{{ $d->decision }}</x-badge>
                            <span class="text-xs text-neutral-500">· {{ $d->decided_at->format('j M Y, H:i') }}</span></p>
                        <p class="text-neutral-700 mt-0.5">{{ $d->reason }}</p>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="text-sm text-neutral-400">No decisions yet. The VP decides first.</p>
        @endforelse

        @if (! $case->isOpen())
            <p class="mt-4 text-sm font-semibold">
                Outcome: {{ $case->outcome }}
                {{ $case->resolution === 'DefaultAfterRounds' ? '— closed as Monitor because the decisions still differed after '.\App\Models\BehaviorCase::MAX_ROUNDS.' rounds.' : '— both agreed.' }}
            </p>
        @endif
    </x-card>

    @if ($myTurn)
        <x-card :title="'Your decision — round '.$case->round" subtitle="Your reason is recorded and shown with the decision. Expel only creates a pending exit request for the Registrar; it never changes the student’s status by itself.">
            <form method="POST" action="{{ route('behavior.cases.decide', $case) }}" class="space-y-4">
                @csrf
                <fieldset>
                    <legend class="text-sm font-semibold mb-2">Decision</legend>
                    <div class="flex flex-col sm:flex-row gap-2">
                        @foreach (['Expel', 'Monitor', 'Close'] as $option)
                            <label class="flex items-center gap-2 border border-neutral-200 rounded-lg px-4 py-3 text-sm font-semibold min-h-11 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                                <input type="radio" name="decision" value="{{ $option }}" required @checked(old('decision') === $option)> {{ $option }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label class="block text-sm font-semibold mb-1" for="case-reason">Reason (required)</label>
                    <textarea id="case-reason" name="reason" rows="3" required minlength="5" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">{{ old('reason') }}</textarea>
                </div>
                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark">Record decision</button>
            </form>
        </x-card>
    @elseif ($case->isOpen())
        <x-card><p class="text-sm text-neutral-500">Waiting for the {{ $case->awaiting === 'VP' ? 'Vice Principal' : 'Principal' }} to decide round {{ $case->round }}.</p></x-card>
    @endif

    @if ($exit)
        <x-card title="Exit request" subtitle="Created by the Expel decision. The Registrar processes it through Transfer/Drop; the student’s status does not change until then.">
            <p class="text-sm"><x-badge :color="$exit->status === 'Pending' ? 'yellow' : 'neutral'">{{ $exit->status }}</x-badge>
                <span class="ml-2 text-neutral-500">created {{ $exit->created_at->format('j M Y') }}</span></p>
            @if ($exit->status === 'Withdrawn')
                <p class="mt-2 text-sm">Withdrawn by {{ $exit->withdrawer?->user?->name }} on {{ $exit->withdrawn_at->format('j M Y') }}. Reason: {{ $exit->withdraw_reason }}</p>
            @elseif ($exit->status === 'Processed')
                <p class="mt-2 text-sm">Processed by {{ $exit->processor?->user?->name }} on {{ $exit->processed_at->format('j M Y') }}.</p>
            @elseif ($role === 'Principal')
                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer font-semibold text-danger">Withdraw this exit request…</summary>
                    <form method="POST" action="{{ route('behavior.exit-requests.withdraw', $exit) }}" class="mt-2 space-y-2"
                          data-confirm="Withdraw the pending exit request for {{ $student->name }}? The VP and the Registrars are notified." onsubmit="return confirm(this.dataset.confirm);">
                        @csrf
                        <label class="block text-xs font-semibold text-neutral-600" for="wd-reason">Reason (required)</label>
                        <textarea id="wd-reason" name="reason" rows="2" required minlength="5" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm"></textarea>
                        <button type="submit" class="bg-danger text-white font-semibold rounded-lg px-4 py-2 text-sm">Withdraw exit request</button>
                    </form>
                </details>
            @endif
        </x-card>
    @endif
</x-app-layout>
