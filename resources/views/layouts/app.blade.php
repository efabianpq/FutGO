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

    {{-- Open Graph: permite preview cuando se comparte un link (WhatsApp, redes). --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FutGO">
    <meta property="og:title" content="{{ $pageTitle ?: 'FutGO' }}">
    <meta property="og:description" content="@yield('og_description', 'Gestión de torneos, equipos y estadísticas en FutGO.')">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    {{-- Fuentes FutGO: Archivo (display/Expanded), Inter (UI), JetBrains Mono (datos) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')

    {{-- PWA: manifest e ícono fusionados en /manifest.json (ver meta tags arriba) --}}
    <link rel="apple-touch-icon" href="/FutGO/pwa/icon-180.png">
    <meta name="apple-mobile-web-app-title" content="FutGO">

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-bg text-text font-sans antialiased min-h-screen flex flex-col">

    <x-nav :user="auth()->user()" />

    <main class="flex-1 pb-20 md:pb-0">
        @if (session('status'))
            <div class="max-w-3xl mx-auto mt-4 px-4">
                <div class="badge-green border border-green/40 bg-green-tint text-green px-4 py-3 rounded-md font-semibold">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="max-w-3xl mx-auto mt-4 px-4">
                <div class="border border-alerta/40 bg-alerta/10 text-alerta px-4 py-3 rounded-md font-semibold">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        {{-- Aviso de perfiles reclamables detectados (Limitación #2). --}}
        @if (session('claim_candidates'))
            <div class="max-w-3xl mx-auto mt-4 px-4">
                <div class="border border-green/40 bg-green-tint text-green px-4 py-3 rounded-md flex items-center justify-between gap-4">
                    <span class="font-semibold">
                        Encontramos {{ session('claim_candidates') }} {{ session('claim_candidates') == 1 ? 'registro' : 'registros' }} a tu nombre que podés reclamar para heredar tu historial.
                    </span>
                    <a href="{{ route('torneos.reclamos.index') }}" class="btn btn-primary btn-sm shrink-0">Ver y reclamar</a>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- ── PWA Overlays (modal iOS + toast instalación) ──────────────────── --}}
    <div x-data>
        {{-- Modal iOS: instrucciones para añadir a la pantalla de inicio --}}
        <div x-show="$store.pwa.showIosModal"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 px-4 pb-6 sm:pb-0"
             @click.self="$store.pwa.closeIosModal()">
            <div class="bg-surface border border-border rounded-xl shadow-modal w-full max-w-sm p-6"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="font-display font-bold text-text text-[17px]">Instalar FutGO</p>
                        <p class="font-mono text-[11px] text-muted mt-0.5">Seguí estos pasos en Safari</p>
                    </div>
                    <button type="button"
                            @click="$store.pwa.closeIosModal()"
                            class="text-muted hover:text-text text-2xl leading-none ml-4"
                            aria-label="Cerrar">&times;</button>
                </div>

                <ol class="space-y-4">
                    <li class="flex items-start gap-3">
                        <span class="w-7 h-7 rounded-full bg-green-tint text-green font-display font-bold text-[13px] flex items-center justify-center shrink-0">1</span>
                        <p class="text-[14px] text-text leading-snug">
                            Pulsá el botón
                            <span class="inline-flex items-center gap-1 font-semibold">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                                Compartir
                            </span>
                            (ícono ↑) en Safari
                        </p>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-7 h-7 rounded-full bg-green-tint text-green font-display font-bold text-[13px] flex items-center justify-center shrink-0">2</span>
                        <p class="text-[14px] text-text leading-snug">
                            Elegí <span class="font-semibold">«Añadir a pantalla de inicio»</span>
                        </p>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="w-7 h-7 rounded-full bg-green-tint text-green font-display font-bold text-[13px] flex items-center justify-center shrink-0">3</span>
                        <p class="text-[14px] text-text leading-snug">
                            Tocá <span class="font-semibold">«Agregar»</span>
                        </p>
                    </li>
                </ol>

                <button type="button"
                        @click="$store.pwa.closeIosModal()"
                        class="mt-6 w-full py-2.5 rounded-md bg-green-tint text-green font-display font-bold text-[14px] uppercase tracking-wide-cta hover:opacity-80 transition-all duration-fast">
                    Entendido
                </button>
            </div>
        </div>

        {{-- Toast: instalación exitosa --}}
        <div x-show="$store.pwa.showToast"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed bottom-20 md:bottom-6 left-1/2 -translate-x-1/2 z-[48] pointer-events-none">
            <div class="flex items-center gap-2.5 bg-surface border border-green/30 text-text font-display font-semibold text-[14px] px-5 py-3 rounded-pill shadow-modal whitespace-nowrap">
                <svg class="w-5 h-5 text-green shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                FutGO instalado correctamente
            </div>
        </div>
    </div>
    {{-- /PWA Overlays --}}

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
