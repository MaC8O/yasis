<x-app-layout :title="$guardian->user->name" subtitle="Guardian profile, linked children, and portal access." badge="Registrar" role="registrar">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Guardian profile">
            <div class="flex items-center gap-3 mb-4">
                <span class="w-12 h-12 rounded-full bg-[#C9A227] text-neutral-900 font-bold flex items-center justify-center">
                    {{ strtoupper(substr($guardian->user->name, 0, 1)) }}
                </span>
                <div>
                    <p class="font-bold">{{ $guardian->user->name }}</p>
                    <p class="text-sm text-neutral-500">{{ $guardian->user->email }} · {{ $guardian->phone ?? 'No phone' }}</p>
                </div>
            </div>
            <x-badge :color="$guardian->user->status === 'Active' ? 'green' : 'yellow'">Portal {{ strtolower($guardian->user->status) }}</x-badge>

            @if ($guardian->user->status !== 'Active')
                <form method="POST" action="{{ route('registrar.guardians.resend-invite', $guardian) }}" class="inline-block ml-2">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-blue-700 hover:underline">Re-send invite</button>
                </form>
            @endif

            <form method="POST" action="{{ route('registrar.guardians.update', $guardian) }}" class="mt-5 pt-5 border-t border-neutral-100 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-semibold mb-1 text-neutral-500 uppercase">Name</label>
                    <input type="text" name="name" value="{{ old('name', $guardian->user->name) }}" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1 text-neutral-500 uppercase">Email</label>
                    <input type="email" name="email" value="{{ old('email', $guardian->user->email) }}" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1 text-neutral-500 uppercase">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $guardian->phone) }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1 text-neutral-500 uppercase">Relationship</label>
                    <input type="text" name="relationship" value="{{ old('relationship', $guardian->relationship) }}" placeholder="Mother / Father / …" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Save contact</button>
                </div>
            </form>
        </x-card>

        <x-card title="Link another student">
            <form method="POST" action="{{ route('registrar.guardians.link', $guardian) }}" class="flex gap-3 items-end">
                @csrf
                <div class="flex-1">
                    <label class="block text-sm font-semibold mb-1">Student ID</label>
                    <input type="text" name="student_id_number" required placeholder="YAS-2026-0001"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <label class="flex items-center gap-1.5 text-sm font-semibold mb-2.5">
                    <input type="checkbox" name="is_primary" value="1"> Primary
                </label>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Link</button>
            </form>
        </x-card>
    </div>

    <x-card title="Linked children">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Student</th>
                    <th class="py-2 font-semibold">Department</th>
                    <th class="py-2 font-semibold">Relationship</th>
                    <th class="py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($guardian->students as $student)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $student->first_name }} {{ $student->last_name }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $student->department?->name }}</td>
                        <td class="py-2.5">
                            @if ($student->pivot->is_primary)
                                <x-badge color="green">Primary</x-badge>
                            @else
                                {{ $guardian->relationship ?? '—' }}
                            @endif
                        </td>
                        <td class="py-2.5 text-right space-x-3 whitespace-nowrap">
                            @unless ($student->pivot->is_primary)
                                <form method="POST" action="{{ route('registrar.guardians.set-primary', [$guardian, $student]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-[#8A6D10] hover:underline">Make primary</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('registrar.guardians.unlink', [$guardian, $student]) }}" class="inline"
                                  onsubmit="return confirm('Unlink {{ $student->first_name }} {{ $student->last_name }} from this guardian?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-[#B0392B] hover:underline">Unlink</button>
                            </form>
                            <a href="{{ route('registrar.students.show', $student) }}" class="text-xs font-semibold text-[#1F573D] hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No children linked yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <a href="{{ route('registrar.guardians.index') }}" class="inline-block text-sm font-semibold text-neutral-500 hover:underline">Back to guardians</a>
</x-app-layout>
