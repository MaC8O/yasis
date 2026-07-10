<x-app-layout title="Announcements" subtitle="Publish registration and records notices — school-wide or targeted." badge="Registrar" role="registrar">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="New announcement" subtitle="Choose an audience, then publish." x-data="{ audience: 'All' }">
            <form method="POST" action="{{ route('registrar.announcements.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-2">AUDIENCE</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (array_merge(['All', 'Staff', 'Guardians', 'Students'], $departments->pluck('name')->all()) as $option)
                            <label class="cursor-pointer">
                                <input type="radio" name="audience" value="{{ $option }}" x-model="audience" class="peer sr-only" @checked($option === 'All')>
                                <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-semibold border border-neutral-200 text-neutral-600 peer-checked:bg-[#1F573D] peer-checked:text-white peer-checked:border-[#1F573D]">
                                    {{ $option }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1">TITLE</label>
                    <input type="text" name="title" required placeholder="e.g. Registration window for 2027 opens Monday" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1">MESSAGE</label>
                    <textarea name="body" rows="5" required placeholder="Write your announcement..." class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm"></textarea>
                </div>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Publish announcement</button>
            </form>
        </x-card>

        <x-card title="Recent announcements" subtitle="{{ $announcements->count() }} posted.">
            <div class="space-y-4">
                @forelse ($announcements as $announcement)
                    <div class="border-b border-neutral-100 last:border-0 pb-4">
                        <p class="font-semibold text-sm">{{ $announcement->title }}</p>
                        <p class="text-sm text-neutral-600 mt-1">{{ $announcement->body }}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <x-badge color="blue">{{ $announcement->audience_type }}</x-badge>
                            <span class="text-xs text-neutral-400">· {{ $announcement->published_at->format('d M') }} · {{ $announcement->author->user->name ?? '—' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No announcements published yet.</p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
