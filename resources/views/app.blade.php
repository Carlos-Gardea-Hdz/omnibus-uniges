<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'UNIGES') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">

    {{-- Prevent theme flash (FOUC): set .dark from storage BEFORE first paint. --}}
    <script>
        (function () {
            try {
                var pref = localStorage.getItem('uniges.theme');
                var systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var dark = pref === 'dark' || (pref !== 'light' && systemDark);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-surface text-fg">
    {{-- Skip link for keyboard/screen-reader users (a11y). --}}
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-accent focus:px-3 focus:py-2 focus:text-accent-fg">
        {{ app()->getLocale() === 'es' ? 'Saltar al contenido' : 'Skip to content' }}
    </a>
    @inertia
</body>
</html>
