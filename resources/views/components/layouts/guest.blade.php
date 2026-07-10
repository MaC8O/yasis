<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ISMS — Yangon Adventist Seminary' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-neutral-900 min-h-screen flex items-center justify-center p-4 sm:p-8">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8">
        {{ $slot }}
    </div>
</body>
</html>
