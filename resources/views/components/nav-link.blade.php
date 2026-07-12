@props(['route', 'label'])
@php $active = request()->routeIs($route) || request()->routeIs($route.'.*'); @endphp
<a href="{{ Route::has($route) ? route($route) : '#' }}"
    class="block rounded-xl px-4 py-2.5 text-sm font-medium transition
        {{ $active ? 'bg-[#1F573D] text-white font-semibold' : 'text-neutral-300 hover:bg-white/5 hover:text-white' }}">
    {{ $label }}
</a>
