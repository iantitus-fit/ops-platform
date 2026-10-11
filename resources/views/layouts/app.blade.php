<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        <style>
            body { font-family: system-ui, sans-serif; margin: 0; padding: 1.5rem; background: #f9fafb; color: #111827; }
            main { max-width: 40rem; margin: 0 auto; }
        </style>
    </head>
    <body>
        <main>
            {{ $slot }}
        </main>
    </body>
</html>
