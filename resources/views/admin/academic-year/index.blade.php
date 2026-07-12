<x-app-layout title="Academic Year" subtitle="Set the active academic year and its four terms." badge="Admin" role="admin">
    <x-card title="Add academic year" subtitle="Creates 4 nine-week terms automatically, starting from the date below.">
        <form method="POST" action="{{ route('admin.academic-year.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1">Year label</label>
                <input type="text" name="year_label" placeholder="2026-2027" required
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Term 1 start date</label>
                <input type="date" name="start_date" required
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Create year</button>
        </form>
    </x-card>

    @foreach ($academicYears as $year)
        <x-card>
            <div x-data="{ editing: false }">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-bold">{{ $year->year_label }}</h2>
                        @if ($year->is_active)
                            <x-badge color="green">Active</x-badge>
                        @endif
                    </div>
                    <div class="flex items-center gap-4">
                        @unless ($year->is_active)
                            <form method="POST" action="{{ route('admin.academic-year.activate', $year) }}">
                                @csrf
                                <button type="submit" class="text-sm font-semibold text-[#1F573D] hover:underline">Set as active</button>
                            </form>
                        @endunless
                        <button type="button" @click="editing = !editing" class="text-sm font-semibold text-blue-700 hover:underline">
                            <span x-text="editing ? 'Close editor' : 'Edit'"></span>
                        </button>
                        @if (! $year->is_active && $year->sections->isEmpty())
                            <form method="POST" action="{{ route('admin.academic-year.destroy', $year) }}"
                                onsubmit="return confirm('Delete {{ $year->year_label }} and its terms? This cannot be undone.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-sm font-semibold text-red-700 hover:underline">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div x-show="editing" x-cloak>
                    <form method="POST" action="{{ route('admin.academic-year.update', $year) }}" class="space-y-4 mb-4 border border-neutral-200 rounded-xl p-4 bg-neutral-50">
                        @csrf @method('PUT')
                        <div class="sm:w-1/3">
                            <label class="block text-sm font-semibold mb-1">Year label</label>
                            <input type="text" name="year_label" value="{{ $year->year_label }}" required
                                class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-sm">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            @foreach ($year->terms->sortBy('sequence') as $i => $term)
                                <div>
                                    <p class="text-sm font-semibold mb-1">{{ $term->name }}</p>
                                    <input type="hidden" name="terms[{{ $i }}][id]" value="{{ $term->id }}">
                                    <label class="block text-xs text-neutral-500 mb-0.5">Start</label>
                                    <input type="date" name="terms[{{ $i }}][start_date]" value="{{ $term->start_date->toDateString() }}" required
                                        class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm mb-2">
                                    <label class="block text-xs text-neutral-500 mb-0.5">End</label>
                                    <input type="date" name="terms[{{ $i }}][end_date]" value="{{ $term->end_date->toDateString() }}" required
                                        class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm">
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Save changes</button>
                    </form>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Term</th>
                            <th class="py-2 font-semibold">Start</th>
                            <th class="py-2 font-semibold">End</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($year->terms->sortBy('sequence') as $term)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5">{{ $term->name }}</td>
                                <td class="py-2.5">{{ $term->start_date->format('M j, Y') }}</td>
                                <td class="py-2.5">{{ $term->end_date->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endforeach
</x-app-layout>
