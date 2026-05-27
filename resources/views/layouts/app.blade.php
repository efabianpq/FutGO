<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $brand = config('app.name');
        $pageTitle = trim($__env->yieldContent('title'));
        $fullTitle = ($pageTitle === '' || $pageTitle === $brand) ? $brand : ($pageTitle . ' · ' . $brand);
    @endphp
    <title>{{ $fullTitle }}</title>

    {{-- Google Fonts: Barlow Condensed (display), DM Sans (body), JetBrains Mono (datos) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=DM+Sans:wght@400;500;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-bone text-ink font-body antialiased min-h-screen flex flex-col">

    <x-nav :user="auth()->user()" />

    <main class="flex-1">
        @if (session('status'))
            <div class="max-w-3xl mx-auto mt-4 px-4">
                <div class="bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold tracking-[.02em]">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-line bg-bone-soft mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-ink-mute">
            <p class="font-display font-bold text-[14px] uppercase tracking-[.04em]">
                Pachón<span class="text-gol">·</span>Mundial
            </p>
            <p class="font-mono text-[11px] tracking-wide-eyebrow uppercase">
                @SoyPachón — Polla del Mundial 2026
            </p>
        </div>
    </footer>
</body>
</html>
