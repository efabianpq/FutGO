<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $fullTitle = $pageTitle ?: config('app.name');
    @endphp
    <title>{{ $fullTitle }}</title>

    {{-- Open Graph / Twitter: vista previa rica al compartir el link (WhatsApp, FB, etc.) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FutGO">
    <meta property="og:title" content="{{ $pageTitle ?: 'FutGO' }}">
    <meta property="og:description" content="@yield('og_description', 'Seguí el torneo en vivo: tabla, resultados, próximos partidos y goleadores.')">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- PWA --}}
    <link rel="manifest" href="/FutGO/pwa/manifest.webmanifest">
    <meta name="theme-color" content="#0b0f14">
    <link rel="apple-touch-icon" href="/FutGO/pwa/icon-180.png">

    <style>[x-cloak]{ display:none !important; }</style>
</head>
<body class="bg-bone-soft text-ink font-sans antialiased min-h-screen flex flex-col">

    <header class="sticky top-0 z-40 bg-pitch border-b border-line/20">
        <div class="max-w-3xl mx-auto px-4 h-14 flex items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="shrink-0"><x-logo size="sm" /></a>
            <a href="{{ route('login') }}" class="font-display font-semibold text-[13px] text-bone/90 hover:text-bone uppercase tracking-wide-label">Ingresar →</a>
        </div>
    </header>

    <main class="flex-1">@yield('content')</main>

    <footer class="py-8 bg-pitch mt-10">
        <div class="max-w-3xl mx-auto px-4 flex flex-wrap items-center justify-between gap-3">
            <x-logo size="sm" />
            <span class="font-mono text-[11px] text-bone/60">© {{ date('Y') }} FutGO · Donde crece el fútbol amateur</span>
        </div>
    </footer>

</body>
</html>
