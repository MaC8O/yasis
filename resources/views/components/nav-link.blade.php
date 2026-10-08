@props(['route', 'label', 'icon' => 'dot'])
@php $active = request()->routeIs($route) || request()->routeIs($route.'.*'); @endphp
<a href="{{ Route::has($route) ? route($route) : '#' }}" @if ($active) aria-current="page" @endif
    class="group flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-colors
        {{ $active ? 'bg-brand text-white font-semibold shadow-sm shadow-black/20' : 'text-neutral-300 hover:bg-white/5 hover:text-white' }}">
    <x-icon :name="$icon" class="w-[18px] h-[18px] shrink-0 {{ $active ? 'text-gold' : 'text-neutral-500 group-hover:text-neutral-300' }}" />
    <span class="truncate">{{ $label }}</span>
</a>
