<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#00c853">
    <link rel="manifest" href="/manifest.json">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Anti-FOUC: aplica el tema ANTES de pintar (default light) --}}
    <script>
        (function(){
            var t = localStorage.getItem('futgo-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $fullTitle = $pageTitle ?: config('app.name');
    @endphp
    <title>{{ $fullTitle }}</title>

    {{-- Fuentes FutGO --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{ display:none !important; }</style>
</head>
<body class="bg-bg text-text font-sans antialiased">

    {{-- ══ TOPBAR ══════════════════════════════════════════════════════════ --}}
    <header class="sticky top-0 z-40 flex items-center gap-4 h-16 px-5
                   bg-bg/80 backdrop-blur-md border-b border-border"
            style="padding-top: env(safe-area-inset-top);"
            x-data="{ mopen: false }">

        <a href="{{ route('home') }}" class="shrink-0"><x-logo size="sm" /></a>
        <div class="flex-1"></div>

        {{-- Desktop nav --}}
        <nav class="hidden md:flex gap-0.5">
            <a href="{{ route('torneos.public.index') }}" class="px-3.5 py-2 rounded-xs text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2 transition-all">Torneos</a>
            <a href="{{ route('social.oportunidades.index') }}" class="px-3.5 py-2 rounded-xs text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2 transition-all">Oportunidades</a>
        </nav>

        <div class="flex items-center gap-2">
            <x-theme-toggle />
            @guest
                <a href="{{ route('login') }}"    class="hidden sm:inline-flex btn btn-secondary btn-sm">Iniciar sesión</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Crear cuenta</a>
            @else
                <a href="{{ route('inicio') }}" class="btn btn-primary btn-sm">Mi panel</a>
            @endguest
        </div>

        {{-- Mobile hamburger --}}
        <button class="md:hidden btn btn-icon btn-ghost btn-sm"
                @click="mopen = !mopen" aria-label="Menú">
            <svg x-show="!mopen"           fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="mopen" x-cloak   fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        {{-- Mobile drawer --}}
        <div x-show="mopen" x-cloak
             class="absolute top-16 inset-x-0 bg-surface border-b border-border p-4 space-y-1 md:hidden shadow-card-2">
            <a href="{{ route('torneos.public.index') }}" @click="mopen=false" class="block px-3 py-2.5 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Torneos</a>
            <a href="{{ route('social.oportunidades.index') }}" @click="mopen=false" class="block px-3 py-2.5 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Oportunidades</a>
            @guest
                <div class="pt-2 grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}"    class="btn btn-secondary btn-block">Iniciar sesión</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-block">Crear cuenta</a>
                </div>
            @else
                <a href="{{ route('inicio') }}" class="btn btn-primary btn-block mt-2">Mi panel</a>
            @endguest
        </div>
    </header>

    <main>@yield('content')</main>

    {{-- ══ FOOTER ═══════════════════════════════════════════════════════════ --}}
    <footer class="py-12 bg-surface border-t border-border">
        <div class="max-w-[1200px] mx-auto px-6 flex flex-wrap items-center justify-between gap-5">
            <x-logo size="sm" />
            <div class="flex flex-wrap gap-5">
                <a href="{{ route('torneos.public.index') }}" class="text-[14px] font-semibold text-muted hover:text-text transition-all">Torneos</a>
                <a href="{{ route('social.oportunidades.index') }}" class="text-[14px] font-semibold text-muted hover:text-text transition-all">Oportunidades</a>
                @guest
                    <a href="{{ route('login') }}"    class="text-[14px] font-semibold text-muted hover:text-text transition-all">Iniciar sesión</a>
                    <a href="{{ route('register') }}" class="text-[14px] font-semibold text-muted hover:text-text transition-all">Crear cuenta</a>
                @endguest
            </div>
            <span class="font-mono text-[12px] text-subtle">© {{ date('Y') }} FutGO · Donde crece el fútbol amateur</span>
        </div>
    </footer>

</body>
</html>
