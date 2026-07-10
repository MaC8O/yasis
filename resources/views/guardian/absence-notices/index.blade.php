<x-app-layout title="Notify School of Absence" subtitle="Let the school know in advance when your child will be away." badge="Guardian · Read + Notify" role="guardian">
    <x-child-switcher :children="$children" :child="$child" route="guardian.absence-notices.index" />

    <x-card>
        <p class="text-sm text-yellow-800 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3">
            Not a request — this notifies your homeroom teacher and flags the date(s); Excused applies at attendance time.
        </p>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="New absence notice" :subtitle="'For '.$child->first_name.' · '.($child->department->name ?? '')">
            <form method="POST" action="{{ route('guardian.absence-notices.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="student_id" value="{{ $child->id }}">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-500 mb-1">FROM</label>
                        <input type="date" name="from_date" required min="{{ today()->toDateString() }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-neutral-500 mb-1">TO</label>
                        <input type="date" name="to_date" required min="{{ today()->toDateString() }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1">REASON</label>
                    <textarea name="reason" rows="3" placeholder="e.g. Family travel, medical appointment..." class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm"></textarea>
                </div>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Submit notice</button>
                <span class="text-xs text-neutral-400 ml-2">Your teacher will be notified right away.</span>
            </form>
        </x-card>

        <x-card title="My notices" :subtitle="'Status of past requests for '.$child->first_name.'.'">
            <div class="space-y-4">
                @forelse ($notices as $notice)
                    <div x-data="{ editing: false }">
                        <p class="font-semibold text-sm">{{ $notice->from_date->format('d M') }} – {{ $notice->to_date->format('d M Y') }}</p>
                        <p class="text-xs text-neutral-500">{{ $notice->reason ?? 'No reason given' }}</p>
                        <div class="mt-1">
                            <x-badge :color="$notice->status === 'Acknowledged' ? 'green' : ($notice->status === 'Cancelled' ? 'pink' : 'yellow')">
                                {{ $notice->status === 'Acknowledged' ? 'Acknowledged by Homeroom Teacher' : $notice->status }}
                            </x-badge>
                        </div>
                        @if (in_array($notice->status, ['Submitted', 'Acknowledged']) && $notice->from_date->isFuture())
                            <div class="flex gap-3 mt-2">
                                <button type="button" @click="editing = !editing" class="text-xs font-semibold text-neutral-700 border border-neutral-300 rounded-lg px-3 py-1.5">Edit</button>
                                <form method="POST" action="{{ route('guardian.absence-notices.cancel', $notice) }}" onsubmit="return confirm('Cancel this absence notice?');">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-red-700 border border-red-300 rounded-lg px-3 py-1.5">Cancel</button>
                                </form>
                            </div>
                            <form x-show="editing" method="POST" action="{{ route('guardian.absence-notices.update', $notice) }}" class="grid grid-cols-3 gap-2 mt-3">
                                @csrf @method('PUT')
                                <input type="date" name="from_date" value="{{ $notice->from_date->toDateString() }}" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                <input type="date" name="to_date" value="{{ $notice->to_date->toDateString() }}" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                <button type="submit" class="text-xs font-semibold bg-[#1F573D] text-white rounded-lg px-3 py-1.5">Save</button>
                            </form>
                        @else
                            <p class="text-xs text-neutral-400 mt-1">
                                {{ $notice->status === 'Cancelled' ? 'Withdrawn before the date.' : 'Locked — date has passed.' }}
                            </p>
                        @endif
                        <hr class="mt-4 border-neutral-100">
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No absence notices submitted yet.</p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
