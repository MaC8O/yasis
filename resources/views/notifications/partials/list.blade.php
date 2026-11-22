{{-- A list of personal notices. $notifications (collection or paginator). --}}
<x-card>
    @forelse ($notifications as $notice)
        <a href="{{ route('notifications.open', $notice->id) }}"
           class="block py-3 border-b border-neutral-100 last:border-0 -mx-2 px-2 rounded-lg hover:bg-neutral-50 {{ $notice->read_at ? '' : 'bg-brand-soft/40' }}">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-ink">
                    @unless ($notice->read_at)<span class="inline-block w-2 h-2 rounded-full bg-brand mr-1.5 align-middle" aria-label="Unread"></span>@endunless
                    {{ $notice->data['title'] ?? 'Notice' }}
                </p>
                <span class="text-xs text-neutral-400 shrink-0">{{ $notice->created_at->diffForHumans() }}</span>
            </div>
            <p class="text-sm text-neutral-600 mt-0.5">{{ $notice->data['body'] ?? '' }}</p>
        </a>
    @empty
        <p class="text-sm text-neutral-400">You have no notices.</p>
    @endforelse
</x-card>
