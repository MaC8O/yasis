@php
    $role = $role ?? auth()->user()?->getRoleNames()->first();
    $nav = config("portal_nav.$role", ['portal_label' => 'Portal', 'items' => []]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ISMS' }} — Yangon Adventist Seminary</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f4f4f0] min-h-screen flex text-neutral-900">
    <aside class="w-[252px] shrink-0 bg-[#141a17] text-white min-h-screen flex flex-col px-6 py-8">
        <div class="mb-8">
            <p class="font-bold text-lg tracking-wide">YASIS ISMS</p>
            <p class="text-sm text-neutral-400 mt-0.5">{{ $nav['portal_label'] }}</p>
        </div>

        <nav class="flex-1 space-y-1">
            @foreach ($nav['items'] as $item)
                <x-nav-link :route="$item['route']" :label="$item['label']" />
            @endforeach
        </nav>

        <div class="flex items-center gap-3 mb-4 px-1">
            @if (auth()->user()?->photo_path)
                <img src="{{ Storage::url(auth()->user()->photo_path) }}" alt=""
                     class="w-9 h-9 rounded-full object-cover border border-neutral-600 shrink-0">
            @else
                <span class="w-9 h-9 rounded-full bg-[#C9A227] text-neutral-900 font-bold text-xs flex items-center justify-center shrink-0">
                    {{ collect(explode(' ', auth()->user()?->name ?? '?'))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </span>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-semibold truncate">{{ auth()->user()?->name }}</p>
                <p class="text-xs text-neutral-400 truncate">{{ auth()->user()?->email }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full bg-white text-neutral-900 font-semibold rounded-xl py-2.5 text-sm">
                Sign out
            </button>
        </form>
    </aside>

    <main class="flex-1 p-8 space-y-6 max-w-[1280px]">
        <div class="bg-white rounded-2xl border border-neutral-200 px-8 py-6 flex items-start justify-between gap-6">
            <div>
                <h1 class="text-3xl font-bold">{{ $title }}</h1>
                @isset($subtitle)
                    <p class="text-neutral-500 mt-1">{{ $subtitle }}</p>
                @endisset
            </div>
            @isset($badge)
                <span class="shrink-0 bg-[#F5E4A8] text-[#141a17] font-semibold text-sm rounded-full px-4 py-2">
                    {{ $badge }}
                </span>
            @endisset
        </div>

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-5 py-3">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-5 py-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
