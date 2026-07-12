@php
    $icons = [
        'building' => '<path d="M3 21h18M6 21V7l6-4 6 4v14M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h.01M15 17h.01"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
        'shield' => '<path d="M12 3l8 3v5c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-3z"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5.2 5.2L3 18l3 3 6.5-6.5a4 4 0 0 0 5.2-5.2l-2.3 2.3-2.7-.7-.7-2.7 2.4-2.2z"/>',
    ];
    $groups = array_keys($schema);
@endphp

<x-app-layout title="System Settings" subtitle="Institution-wide configuration: profile, localization, notifications, security and maintenance." badge="Admin" role="admin">
    <div x-data="{ tab: '{{ $groups[0] }}' }" class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-6 items-start">
        {{-- Section nav --}}
        <x-card class="!p-2 lg:sticky lg:top-6">
            <nav class="space-y-0.5">
                @foreach ($schema as $name => $group)
                    <button type="button" @click="tab = '{{ $name }}'"
                            :class="tab === '{{ $name }}' ? 'bg-[#1F573D] text-white' : 'text-neutral-600 hover:bg-neutral-100'"
                            class="w-full flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-semibold text-left">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$group['icon']] ?? '' !!}</svg>
                        <span>{{ $name }}</span>
                    </button>
                @endforeach
            </nav>
        </x-card>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @foreach ($schema as $name => $group)
                <div x-show="tab === '{{ $name }}'" x-cloak class="space-y-6">
                    <x-card :title="$name" :subtitle="$group['blurb']">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                            @foreach ($group['fields'] as $key => $field)
                                <div class="{{ ($field['full'] ?? false) ? 'sm:col-span-2' : '' }} {{ $field['type'] === 'bool' ? 'sm:col-span-2' : '' }}">
                                    @if ($field['type'] === 'bool')
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <input type="checkbox" name="{{ $key }}" value="1" @checked($settings[$key] === '1')
                                                   class="mt-0.5 w-4 h-4 rounded border-neutral-300 text-[#1F573D] focus:ring-[#1F573D]">
                                            <span>
                                                <span class="block text-sm font-semibold">{{ $field['label'] }}</span>
                                                @isset($field['help'])<span class="block text-xs text-neutral-400 mt-0.5">{{ $field['help'] }}</span>@endisset
                                            </span>
                                        </label>
                                    @else
                                        <label class="block text-sm font-semibold mb-1">{{ $field['label'] }}</label>
                                        @if ($field['type'] === 'select')
                                            <select name="{{ $key }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">
                                                @foreach ($field['options'] as $val => $labelText)
                                                    <option value="{{ $val }}" @selected($settings[$key] === $val)>{{ $labelText }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($field['type'] === 'textarea')
                                            <textarea name="{{ $key }}" rows="3" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">{{ $settings[$key] }}</textarea>
                                        @else
                                            <input type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text')) }}"
                                                   name="{{ $key }}" value="{{ $settings[$key] }}" placeholder="{{ $field['placeholder'] ?? '' }}"
                                                   class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">
                                        @endif
                                        @isset($field['help'])<p class="text-xs text-neutral-400 mt-1">{{ $field['help'] }}</p>@endisset
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($name === 'Notifications & email')
                            <div class="mt-5 pt-5 border-t border-neutral-100 flex items-center gap-3 flex-wrap">
                                <button type="submit" formaction="{{ route('admin.settings.test-smtp') }}"
                                        class="text-sm font-semibold text-[#1F573D] border border-[#1F573D] rounded-lg px-4 py-2 hover:bg-[#1F573D]/5">Test SMTP connection</button>
                                <span class="text-xs {{ $smtpConfigured ? 'text-neutral-400' : 'text-amber-600' }}">
                                    {{ $smtpConfigured ? 'Tests a TCP connection to the SMTP host without sending mail.' : 'No SMTP host set yet — email will not be delivered.' }}
                                </span>
                            </div>
                        @endif
                    </x-card>
                </div>
            @endforeach

            <div class="flex items-center gap-3">
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-2.5 text-sm hover:bg-[#184630]">Save settings</button>
                <span class="text-xs text-neutral-400">Changes apply across all portals.</span>
            </div>
        </form>
    </div>
</x-app-layout>
