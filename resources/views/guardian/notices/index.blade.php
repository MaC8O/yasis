<x-app-layout title="Notices" subtitle="Read school announcements and child-related alerts." badge="Guardian · Read-only access" role="guardian">
    @php $personal = auth()->user()->notifications()->limit(5)->get(); @endphp
    @if ($personal->isNotEmpty())
        <h2 class="text-lg font-bold text-ink">For you</h2>
        @include('notifications.partials.list', ['notifications' => $personal])
        <a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-brand hover:underline">See all your notices →</a>
        <h2 class="text-lg font-bold text-ink">School announcements</h2>
    @endif
    <x-card>
        <div class="space-y-4">
            @forelse ($announcements as $announcement)
                <div class="border-b border-neutral-100 last:border-0 pb-4">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-sm">{{ $announcement->title }}</p>
                        <x-badge color="blue">{{ $announcement->audience_type }}</x-badge>
                    </div>
                    <p class="text-sm text-neutral-600 mt-1">{{ $announcement->body }}</p>
                    <p class="text-xs text-neutral-400 mt-1">{{ $announcement->published_at->format('M j, Y') }} · {{ $announcement->author->user->name ?? '—' }}</p>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No notices yet.</p>
            @endforelse
        </div>
    </x-card>
</x-app-layout>
