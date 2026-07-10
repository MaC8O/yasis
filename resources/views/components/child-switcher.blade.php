@props(['children', 'child', 'route'])
<x-card title="Children">
    <div class="flex flex-wrap items-center gap-3">
        @foreach ($children as $c)
            <a href="{{ route($route, ['child' => $c->id]) }}"
                class="px-4 py-2 rounded-lg text-sm font-semibold border {{ $c->id === $child->id ? 'bg-[#1F573D] text-white border-[#1F573D]' : 'bg-white border-neutral-200 text-neutral-700' }}">
                {{ $c->first_name }} {{ $c->last_name }} <span class="opacity-70">· {{ $c->department->name ?? '' }}</span>
            </a>
        @endforeach
        <x-badge color="blue">Guardian can only view linked children</x-badge>
    </div>
</x-card>
