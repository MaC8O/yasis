@props(['children', 'child', 'route'])
<x-card title="Children">
    <div class="flex flex-wrap items-center gap-3">
        @foreach ($children as $c)
            <a href="{{ route($route, ['child' => $c->id]) }}"
                class="px-4 py-2 rounded-lg text-sm font-semibold border transition-colors {{ $c->id === $child->id ? 'bg-brand text-white border-brand' : 'bg-white border-neutral-200 text-neutral-700 hover:border-brand hover:text-brand' }}">
                {{ $c->name }} <span class="opacity-70">· {{ $c->department->name ?? '' }}</span>
            </a>
        @endforeach
        <x-badge color="blue">Guardian can only view linked children</x-badge>
    </div>
</x-card>
