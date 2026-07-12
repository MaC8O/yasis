@props(['title' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-neutral-200 px-8 py-6']) }}>
    @if ($title)
        <h2 class="text-lg font-bold">{{ $title }}</h2>
    @endif
    @if ($subtitle)
        <p class="text-sm text-neutral-500 mt-1">{{ $subtitle }}</p>
    @endif
    <div class="{{ $title || $subtitle ? 'mt-4' : '' }}">
        {{ $slot }}
    </div>
</div>
