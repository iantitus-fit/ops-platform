<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
        <meta name="color-scheme" content="light">
        <title>{{ $title ?? $businessName }}</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-page font-sans text-body font-normal text-ink">
        <header class="bg-surface">
            <div class="mx-auto max-w-xl px-4 py-4">
                <p class="break-words font-semibold">{{ $businessName }}</p>
            </div>
        </header>

        <main class="mx-auto max-w-xl px-4 pb-12 pt-6">
            {{ $slot }}
        </main>
    </body>
</html>
