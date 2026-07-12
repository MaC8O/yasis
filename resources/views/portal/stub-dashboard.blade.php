<x-app-layout :title="$title" :subtitle="$subtitle" :badge="$badge" :role="$role">
    <x-card title="Module coming in a later phase">
        <p class="text-neutral-600 text-sm">
            You're signed in as <strong>{{ auth()->user()->name }}</strong>. This portal's screens are built phase-by-phase
            per the implementation plan — this dashboard will be filled in when this role's module lands.
        </p>
    </x-card>
</x-app-layout>
