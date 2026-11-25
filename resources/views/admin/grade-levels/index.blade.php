<x-app-layout title="Grade Levels" subtitle="The school's grade levels, in order. Grading, subjects, report cards and fees follow the grade level, not the department." badge="Admin" role="admin">
    <x-card title="Add a grade level" subtitle="It goes to the end of the list; move it into place afterwards. The code can't be changed later, because the school's rules are looked up by it.">
        <form method="POST" action="{{ route('admin.grade-levels.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label for="gl-code" class="block text-sm font-semibold mb-1">Code</label>
                <input id="gl-code" type="text" name="code" value="{{ old('code') }}" maxlength="10" required placeholder="KG1"
                       class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm uppercase">
            </div>
            <div class="sm:col-span-2">
                <label for="gl-name" class="block text-sm font-semibold mb-1">Name</label>
                <input id="gl-name" type="text" name="name" value="{{ old('name') }}" maxlength="60" required placeholder="Kindergarten 1"
                       class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark transition-colors">Add level</button>
        </form>
    </x-card>

    <x-card title="Levels" subtitle="{{ $levels->whereNull('archived_at')->count() }} in use · retired levels stay listed and can be restored. A level can't be retired while any section uses it.">
        <ol class="divide-y divide-neutral-100">
            @foreach ($levels as $level)
                <li x-data="{ editing: false }" class="py-3 {{ $level->isArchived() ? 'opacity-60' : '' }}">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="w-8 text-sm text-neutral-400 tabular-nums">{{ $loop->iteration }}.</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm">
                                {{ $level->name }}
                                @if ($level->isArchived()) <x-badge color="neutral">Retired</x-badge> @endif
                            </p>
                            <p class="text-xs text-neutral-500">Code <code>{{ $level->code }}</code> · {{ $level->sections_count }} {{ Str::plural('section', $level->sections_count) }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @unless ($loop->first)
                                <form method="POST" action="{{ route('admin.grade-levels.move', [$level, 'up']) }}">
                                    @csrf
                                    <button type="submit" aria-label="Move {{ $level->name }} up" class="min-h-[44px] min-w-[44px] rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50">↑</button>
                                </form>
                            @endunless
                            @unless ($loop->last)
                                <form method="POST" action="{{ route('admin.grade-levels.move', [$level, 'down']) }}">
                                    @csrf
                                    <button type="submit" aria-label="Move {{ $level->name }} down" class="min-h-[44px] min-w-[44px] rounded-lg border border-neutral-200 text-sm font-semibold hover:bg-neutral-50">↓</button>
                                </form>
                            @endunless
                            <button type="button" @click="editing = !editing" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-info hover:bg-neutral-50">Rename</button>
                            @if ($level->isArchived())
                                <form method="POST" action="{{ route('admin.grade-levels.restore', $level) }}">
                                    @csrf
                                    <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-brand hover:bg-neutral-50">Restore</button>
                                </form>
                            @elseif ($level->sections_count === 0)
                                <form method="POST" action="{{ route('admin.grade-levels.retire', $level) }}"
                                      data-confirm="Retire {{ $level->name }}? It can no longer be chosen for a section. You can restore it later."
                                      onsubmit="return confirm(this.dataset.confirm);">
                                    @csrf
                                    <button type="submit" class="min-h-[44px] px-4 rounded-lg border border-neutral-200 text-sm font-semibold text-warning hover:bg-neutral-50">Retire</button>
                                </form>
                            @else
                                <span class="text-xs text-neutral-400" title="Move its sections to another level first">In use, can't be retired</span>
                            @endif
                        </div>
                    </div>
                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.grade-levels.update', $level) }}" class="mt-3 flex flex-col sm:flex-row gap-3 sm:items-end sm:pl-12">
                        @csrf @method('PUT')
                        <div class="flex-1">
                            <label for="gl-rename-{{ $level->id }}" class="block text-xs font-semibold mb-1">New name for {{ $level->code }}</label>
                            <input id="gl-rename-{{ $level->id }}" type="text" name="name" value="{{ $level->name }}" maxlength="60" required
                                   class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        </div>
                        <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save name</button>
                    </form>
                </li>
            @endforeach
        </ol>
    </x-card>
</x-app-layout>
