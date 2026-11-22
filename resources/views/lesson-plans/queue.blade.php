@php
    $u = auth()->user();
    $badge = match (true) { $u->hasRole('vp_academic') => 'VP', $u->hasRole('principal') => 'Principal', default => 'Admin' };
@endphp
<x-app-layout title="Lesson Plan Reviews"
              :subtitle="$canReview ? 'Plans waiting for your review, oldest first. Open a plan to approve it or ask for changes.' : 'Plans waiting for the Vice Principal’s review, oldest first. Read only.'"
              :badge="$badge">
    @include('lesson-plans.partials.tabs', ['active' => 'queue', 'queueCount' => $plans->count()])

    <x-card title="Awaiting review">
        @if ($plans->isEmpty())
            <p class="text-sm text-neutral-400">Nothing is waiting for review.</p>
        @else
            @include('lesson-plans.partials.plan-list', ['plans' => $plans])
        @endif
    </x-card>
</x-app-layout>
