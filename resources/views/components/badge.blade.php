@props(['color' => 'blue'])
@php
    $colors = [
        'blue' => 'bg-[#DCE7F9] text-[#1e3a6e]',
        'yellow' => 'bg-[#F5E4A8] text-[#5c4a0a]',
        'pink' => 'bg-[#F6D9D9] text-[#7a2020]',
        'green' => 'bg-[#D7ECD9] text-[#1f4d2c]',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-block rounded-full px-3 py-1.5 text-sm font-medium '.($colors[$color] ?? $colors['blue'])]) }}>
    {{ $slot }}
</span>
