<x-app-layout title="Guardian Management" subtitle="Link guardians to students and manage parent portal access." badge="Registrar" role="registrar">
    @if ($linkStudent)
        <x-card title="Link a guardian to {{ $linkStudent->name }}" subtitle="Pick a guardian below and use its 'Link student' action, or add a new guardian.">
            <p class="text-sm text-neutral-500">Student ID: <span class="font-semibold text-neutral-800">{{ $linkStudent->student_id_number }}</span></p>
        </x-card>
    @endif

    <x-card title="Add guardian">
        <form method="POST" action="{{ route('registrar.guardians.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Name</label>
                <input type="text" name="name" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Email</label>
                <input type="email" name="email" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Relationship</label>
                <input type="text" name="relationship" placeholder="Mother" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Link student ID (optional)</label>
                <input type="text" name="student_id_number" value="{{ $linkStudent?->student_id_number }}" placeholder="YAS-2026-0001" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Add Guardian</button>
        </form>
    </x-card>

    <x-card>
        <form method="GET" class="flex gap-4 items-end mb-4">
            <div class="flex-1">
                <label class="block text-sm font-semibold mb-1">Search guardian</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Filter</button>
        </form>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Guardian</th>
                    <th class="py-2 font-semibold">Email</th>
                    <th class="py-2 font-semibold">Children</th>
                    <th class="py-2 font-semibold">Portal</th>
                    <th class="py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($guardians as $guardian)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $guardian->user?->name }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $guardian->user?->email }}</td>
                        <td class="py-2.5">{{ $guardian->students->count() }}</td>
                        <td class="py-2.5">
                            <x-badge :color="$guardian->user?->status === 'Active' ? 'green' : 'yellow'">{{ $guardian->user?->status }}</x-badge>
                        </td>
                        <td class="py-2.5 text-right">
                            <a href="{{ route('registrar.guardians.show', $guardian) }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-neutral-400">No guardians found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $guardians->links() }}</div>
    </x-card>
</x-app-layout>
