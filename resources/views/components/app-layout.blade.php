@php
    $role = $role ?? auth()->user()?->getRoleNames()->first();
    $nav = config("portal_nav.$role", ['portal_label' => 'Portal', 'items' => []]);
    $initials = collect(explode(' ', auth()->user()?->name ?? '?'))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');

    // Flash messages: 'status' is the app-wide success key; 'warning' / 'error' are also honoured.
    $flashes = collect([
        ['key' => 'status', 'icon' => 'check', 'class' => 'bg-success-soft border-success-line text-success'],
        ['key' => 'warning', 'icon' => 'alert', 'class' => 'bg-warning-soft border-warning-line text-warning'],
        ['key' => 'error', 'icon' => 'alert', 'class' => 'bg-danger-soft border-danger-line text-danger'],
    ])->filter(fn ($f) => session($f['key']));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141a17">
    <title>{{ $title ?? 'ISMS' }} — Yangon Adventist Seminary</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas min-h-screen text-neutral-900 antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:bg-white focus:text-brand focus:font-semibold focus:rounded-lg focus:px-4 focus:py-2 focus:shadow-lg">
        Skip to content
    </a>

    {{-- Mobile top bar (spec 3.5: sidebar becomes a hamburger drawer below the desktop breakpoint) --}}
    <header class="lg:hidden sticky top-0 z-30 bg-ink text-white flex items-center gap-2 pl-1 pr-4 h-14">
        <button type="button" @click="sidebarOpen = true" aria-label="Open menu"
                class="w-11 h-11 flex items-center justify-center shrink-0 rounded-lg hover:bg-white/5">
            <x-icon name="menu" class="w-6 h-6" />
        </button>
        <span class="w-7 h-7 rounded-lg bg-gold text-ink font-extrabold text-sm flex items-center justify-center shrink-0">Y</span>
        <p class="font-bold tracking-wide">YASIS ISMS</p>
        <p class="text-xs text-neutral-400 truncate">· {{ $nav['portal_label'] }}</p>
    </header>

    {{-- Drawer backdrop --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         x-transition.opacity class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

    <div class="flex min-h-screen">
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-50 w-[260px] shrink-0 bg-ink text-white flex flex-col px-4 py-6 overflow-y-auto
                  transform transition-transform duration-200 -translate-x-full lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen">
        <div class="mb-7 px-2 flex items-start justify-between">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-gold text-ink font-extrabold text-lg flex items-center justify-center shrink-0">Y</span>
                <div>
                    <p class="font-bold tracking-wide leading-tight">YASIS ISMS</p>
                    <p class="text-xs text-neutral-400 mt-0.5">{{ $nav['portal_label'] }}</p>
                </div>
            </div>
            <button type="button" @click="sidebarOpen = false" aria-label="Close menu"
                    class="lg:hidden w-11 h-11 -mr-2 -mt-1 flex items-center justify-center rounded-lg text-neutral-400 hover:text-white hover:bg-white/5">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 space-y-0.5" aria-label="Main">
            @foreach ($nav['items'] as $item)
                <x-nav-link :route="$item['route']" :label="$item['label']" :icon="$item['icon'] ?? 'dot'" />
            @endforeach
        </nav>

        <div class="mt-6 pt-5 border-t border-white/10">
            <div class="flex items-center gap-3 mb-4 px-2">
                @if (auth()->user()?->photo_path)
                    <img src="{{ Storage::url(auth()->user()->photo_path) }}" alt=""
                         class="w-9 h-9 rounded-full object-cover border border-neutral-600 shrink-0">
                @else
                    <span class="w-9 h-9 rounded-full bg-gold text-ink font-bold text-xs flex items-center justify-center shrink-0">
                        {{ $initials }}
                    </span>
                @endif
                <div class="min-w-0">
                    <p class="text-sm font-semibold truncate">{{ auth()->user()?->name }}</p>
                    <p class="text-xs text-neutral-400 truncate">{{ auth()->user()?->email }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 bg-white/10 hover:bg-white/15 text-white font-semibold rounded-xl py-2.5 text-sm transition-colors">
                    <x-icon name="logout" class="w-4 h-4" />
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    <main id="main" tabindex="-1" class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 space-y-6 max-w-[1280px] focus:outline-none">
        <div class="relative overflow-hidden bg-white rounded-2xl border border-neutral-200 px-5 py-5 sm:px-8 sm:py-6 flex items-start justify-between gap-4 sm:gap-6 flex-wrap">
            <span class="absolute inset-y-0 left-0 w-1.5 bg-brand" aria-hidden="true"></span>
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-ink">{{ $title }}</h1>
                @isset($subtitle)
                    <p class="text-neutral-500 mt-1">{{ $subtitle }}</p>
                @endisset
            </div>
            @isset($badge)
                <span class="shrink-0 bg-tint-yellow text-ink font-semibold text-sm rounded-full px-4 py-2">
                    {{ $badge }}
                </span>
            @endisset
        </div>

        @foreach ($flashes as $flash)
            <div x-data="{ show: true }" x-show="show" x-transition.opacity.duration.200ms role="status"
                 class="flex items-start gap-3 border text-sm rounded-xl pl-4 pr-2 py-2.5 {{ $flash['class'] }}">
                <x-icon :name="$flash['icon']" class="w-5 h-5 mt-0.5" />
                <p class="flex-1 py-0.5">{{ session($flash['key']) }}</p>
                <button type="button" @click="show = false" aria-label="Dismiss"
                        class="w-7 h-7 flex items-center justify-center rounded-lg opacity-70 hover:opacity-100 hover:bg-black/5">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
        @endforeach
        @if ($errors->any())
            <div role="alert" class="flex items-start gap-3 bg-danger-soft border border-danger-line text-danger text-sm rounded-xl px-4 py-3">
                <x-icon name="alert" class="w-5 h-5 mt-0.5" />
                <div>
                    <p class="font-semibold">Please fix the following {{ Str::plural('issue', $errors->count()) }}:</p>
                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{ $slot }}

        <footer class="pt-2 pb-1 text-xs text-neutral-400 flex flex-wrap justify-between gap-2">
            <span>© {{ now()->year }} Yangon Adventist Seminary · Integrated School Management System</span>
            <span>{{ now()->format('l, j F Y') }}</span>
        </footer>
    </main>
    </div>
</body>
</html>
