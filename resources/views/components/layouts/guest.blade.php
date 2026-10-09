<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141a17">
    <title>{{ $title ?? 'ISMS — Yangon Adventist Seminary' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink min-h-screen flex flex-col items-center justify-center p-4 sm:p-8 antialiased
             bg-[radial-gradient(ellipse_at_top,var(--color-brand)_0%,transparent_60%)]">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl shadow-black/40 p-8 border-t-4 border-gold">
        {{ $slot }}
    </div>
    <p class="mt-6 text-xs text-neutral-500">© {{ now()->year }} Yangon Adventist Seminary</p>
</body>
</html>
