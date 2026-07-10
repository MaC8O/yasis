<x-app-layout title="Announcements" subtitle="Read school notices addressed to staff, and announce to your assigned classes." badge="Teacher" role="teacher">
    <x-card title="Notices for you" subtitle="School-wide, all-staff, department, and your class notices from leadership and the registrar.">
        <div class="space-y-4">
            @forelse ($received as $notice)
                <div class="border-b border-neutral-100 last:border-0 pb-4">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-sm">{{ $notice->title }}</p>
                        <x-badge color="blue">{{ $notice->audience_type === 'School' ? 'School-wide' : $notice->audience_type }}</x-badge>
                    </div>
                    <p class="text-sm text-neutral-600 mt-1">{{ $notice->body }}</p>
                    <p class="text-xs text-neutral-400 mt-1">{{ $notice->published_at->format('M j, Y H:i') }} · {{ $notice->author->user->name ?? 'School' }}</p>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No notices addressed to you yet.</p>
            @endforelse
        </div>
    </x-card>

    <x-card title="Create announcement">
        <form method="POST" action="{{ route('teacher.announcements.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Target class</label>
                <select name="section_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Title</label>
                <input type="text" name="title" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Message</label>
                <textarea name="body" rows="4" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm"></textarea>
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Publish</button>
        </form>
    </x-card>

    <x-card title="Recent announcements">
        <div class="space-y-4">
            @forelse ($announcements as $announcement)
                <div class="border-b border-neutral-100 last:border-0 pb-4">
                    <p class="font-semibold text-sm">{{ $announcement->title }}</p>
                    <p class="text-sm text-neutral-600 mt-1">{{ $announcement->body }}</p>
                    <p class="text-xs text-neutral-400 mt-1">{{ $announcement->published_at->format('M j, Y H:i') }}</p>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No announcements published yet.</p>
            @endforelse
        </div>
    </x-card>
</x-app-layout>
