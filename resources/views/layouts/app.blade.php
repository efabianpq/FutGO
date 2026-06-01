<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Anti-FOUC del tema: aplica tema antes de pintar (default: light) --}}
    <script>
        (function () {
            var t = localStorage.getItem('futgo-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    @php
        $brand = config('app.name');
        $pageTitle = trim($__env->yieldContent('title'));
        $fullTitle = ($pageTitle === '' || $pageTitle === $brand) ? $brand : ($pageTitle . ' · ' . $brand);
    @endphp
    <title>{{ $fullTitle }}</title>

    {{-- Fuentes FutGO: Archivo (display/Expanded), Inter (UI), JetBrains Mono (datos) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-bg text-text font-sans antialiased min-h-screen flex flex-col">

    <x-nav :user="auth()->user()" />

    <main class="flex-1">
        @if (session('status'))
            <div class="max-w-3xl mx-auto mt-4 px-4">
                <div class="badge-green border border-green/40 bg-green-tint text-green px-4 py-3 rounded-md font-semibold">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-border bg-surface mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 flex flex-col sm:flex-row items-center justify-between gap-3">
            <x-logo size="sm" />
            <p class="font-mono text-[11px] tracking-[.12em] uppercase text-subtle">
                Donde crece el fútbol amateur
            </p>
        </div>
    </footer>
</body>
</html>
