<x-app-layout title="My notifications" subtitle="Notices addressed to you personally, newest first.">
    @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
    @if ($unread > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="text-sm font-semibold text-brand hover:underline">Mark all {{ $unread }} as read</button>
        </form>
    @endif
    @include('notifications.partials.list', ['notifications' => $notifications])
    <div class="text-xs">{{ $notifications->links() }}</div>
</x-app-layout>
