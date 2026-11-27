{{-- "Move to…" for one item: another current category of the same gradebook (not Attendance). $item, $from, $categories, $term. --}}
@php $targets = $categories->reject(fn ($c) => $c->id === $from->id || $c->isAttendance()); @endphp
@if ($targets->isNotEmpty() && ! $term?->is_locked)
    <form method="POST" action="{{ route('teacher.gradebook.assessments.move', $item) }}" class="inline-flex items-center gap-1">
        @csrf @method('PATCH')
        <label for="move-{{ $item->id }}" class="sr-only">Move {{ $item->name }} to</label>
        <select id="move-{{ $item->id }}" name="category_id" class="rounded border border-neutral-200 bg-white px-2 py-1 text-xs min-h-[32px]">
            @foreach ($targets as $target)
                <option value="{{ $target->id }}">{{ $target->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="min-h-[32px] text-xs font-semibold text-brand hover:underline">Move</button>
    </form>
@endif
