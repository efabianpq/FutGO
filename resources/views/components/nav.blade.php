@props([
    'user' => null,  // authenticated user model (null = guest)
])

@php
    $routeName = request()->route()?->getName() ?? '';
    $isActive = fn ($needle) => str_starts_with($routeName, $needle);
    $anyActive = fn (array $needles) => collect($needles)->contains(fn ($n) => str_starts_with($routeName, $n));

    // Navegación modular: solo se muestran enlaces de módulos habilitados.
    $pollaAccess     = $user?->hasPollaAccess() ?? false;
    $torneosAccess   = $user?->hasTorneosAccess() ?? false;
    $isPlatformAdmin = $user?->isAdmin() ?? false;

    // ── Nav agrupado (v3): Inicio · Jugar ▾ · Competir ▾ · Comunidad ▾
    //    Header derecho: 🔍 Buscar · 🔔 Feed · 💬 Mensajes · Avatar ▾ (Perfil/Yo)
    //
    // Cada grupo es un dominio de acción. Las herramientas transversales
    // (búsqueda, novedades, mensajes, cuenta) viven a la derecha, siempre visibles.

    // Grupo "Jugar": todo lo que es organizar y disputar partidos.
    $jugarItems = [];
    // Grupo "Competir": torneos y reputación.
    $competirItems = [];
    // Grupo "Comunidad": descubrimiento de entidades públicas.
    $comunidadItems = [];

    if ($torneosAccess) {
        $jugarItems = [
            ['route' => 'social.oportunidades.index', 'label' => 'Oportunidades', 'starts' => 'social.oportunidades', 'desc' => 'Buscá o publicá rival, jugadores o refuerzos'],
            ['route' => 'social.amistosos.index',     'label' => 'Amistosos',     'starts' => 'social.amistosos',     'desc' => 'Reportá resultados y resolvé disputas'],
            ['route' => 'social.oportunidades.express','label' => 'Modo rápido ⚡','starts' => 'social.oportunidades.express', 'desc' => '¿Necesitás rival para mañana?'],
            ['route' => 'social.agenda.index',        'label' => 'Agenda',        'starts' => 'social.agenda',        'desc' => 'Todo lo programado, en un solo lugar'],
        ];
        $competirItems = [
            ['route' => 'torneos.index',       'label' => 'Mis Torneos',   'starts' => 'torneos.index',  'desc' => 'Tus torneos en curso e históricos'],
            ['route' => 'torneos.public.index','label' => 'Buscar Torneo', 'starts' => 'torneos.public', 'desc' => 'Explorá torneos abiertos a inscripción'],
            ['route' => 'torneos.ranking',     'label' => 'Ranking de la plataforma', 'starts' => 'torneos.ranking','desc' => 'Mejores jugadores de la plataforma'],
        ];
        $comunidadItems = [
            ['route' => 'social.canchas.index','label' => 'Canchas',                'starts' => 'social.canchas', 'desc' => 'Catálogo de canchas de la comunidad'],
            ['route' => 'social.search',       'label' => 'Buscar jugadores y clubes','starts' => 'social.search','desc' => 'Encontrá personas, equipos y torneos'],
        ];
    }

    $jugarActive     = $anyActive(['social.oportunidades', 'social.amistosos', 'social.agenda']);
    $competirActive  = $anyActive(['torneos.index', 'torneos.public', 'torneos.ranking']);
    $comunidadActive = $anyActive(['social.canchas', 'social.search']);
    $inicioActive    = $isActive('dashboard');
    $profileActive   = $anyActive(['torneos.mi-carrera', 'torneos.mis-equipos', 'profile.', 'torneos.reclamos']);

    // Subitems del dropdown "Pronósticos" (módulo polla + admin de plataforma).
    $pronosItems = [];
    if ($pollaAccess) {
        $pronosItems[] = ['route' => 'predictions.index', 'label' => 'Mis Pronósticos', 'starts' => 'predictions'];
        $pronosItems[] = ['route' => 'audit.index',       'label' => 'Auditoría',       'starts' => 'audit'];
        $pronosItems[] = ['route' => 'how-it-works',      'label' => '¿Cómo funciona?', 'starts' => 'how-it-works'];
        if ($isPlatformAdmin) {
            $pronosItems[] = ['route' => 'admin.dashboard', 'label' => 'Admin', 'starts' => 'admin.dashboard'];
        }
    }
    $pronosActive = $anyActive(['predictions', 'audit', 'how-it-works', 'admin.dashboard']);

    // Destino del logo / Inicio: dashboard para torneos; degrada con gracia.
    if ($torneosAccess) {
        $homeRoute = route('dashboard');
    } elseif ($pollaAccess) {
        $homeRoute = route('predictions.index');
    } else {
        $homeRoute = route('profile.show');
    }
@endphp

@if ($user)
    {{-- ════════════════════════ Auth nav (v3) ════════════════════════ --}}
    <nav class="sticky top-0 z-40 bg-bg/80 backdrop-blur-md border-b border-border"
         x-data="{ open: false, profileOpen: false, searchOpen: false, jugarOpen: false, competirOpen: false, comunidadOpen: false, pronosOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-3">

                {{-- Brand --}}
                <a href="{{ $homeRoute }}" class="shrink-0">
                    <x-logo size="sm" />
                </a>

                {{-- ── Nav principal (desktop): 4 dominios ── --}}
                <nav class="hidden md:flex items-center gap-1">
                    @if ($torneosAccess)
                        {{-- Inicio --}}
                        <a href="{{ $homeRoute }}"
                           class="flex items-center gap-1.5 px-3 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $inicioActive ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-8 9 8M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
                            Inicio
                        </a>

                        {{-- Jugar ▾ --}}
                        <x-nav-dropdown label="Jugar" :active="$jugarActive" state="jugarOpen" :items="$jugarItems" :is-active="$isActive">
                            <x-slot:icon>
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.5 4.5L19 8l-3 3.5L17 16l-5-2-5 2 1-4.5L5 8l4.5-.5z"/></svg>
                            </x-slot:icon>
                        </x-nav-dropdown>

                        {{-- Competir ▾ --}}
                        <x-nav-dropdown label="Competir" :active="$competirActive" state="competirOpen" :items="$competirItems" :is-active="$isActive">
                            <x-slot:icon>
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM7 6H4v1a3 3 0 003 3M17 6h3v1a3 3 0 01-3 3"/></svg>
                            </x-slot:icon>
                        </x-nav-dropdown>

                        {{-- Comunidad ▾ --}}
                        <x-nav-dropdown label="Comunidad" :active="$comunidadActive" state="comunidadOpen" :items="$comunidadItems" :is-active="$isActive">
                            <x-slot:icon>
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-4-4M9 20H4v-1a4 4 0 014-4h2a4 4 0 014 4v1H9zm6-9a3 3 0 100-6 3 3 0 000 6zm-6 0a3 3 0 100-6 3 3 0 000 6z"/></svg>
                            </x-slot:icon>
                        </x-nav-dropdown>
                    @endif

                    {{-- Pronósticos ▾ (módulo polla) --}}
                    @if (! empty($pronosItems))
                        <div class="relative" @click.outside="pronosOpen = false">
                            <button type="button" @click="pronosOpen = !pronosOpen"
                                    class="flex items-center gap-1 px-3 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $pronosActive ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                                Pronósticos
                                <svg class="w-3.5 h-3.5" :class="pronosOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div x-show="pronosOpen" x-cloak x-transition
                                 class="absolute left-0 mt-1 w-52 bg-surface border border-border rounded-md shadow-card-2 py-1 z-50">
                                @foreach ($pronosItems as $item)
                                    <a href="{{ route($item['route']) }}"
                                       class="block px-4 py-2 text-[14px] font-semibold {{ $isActive($item['starts']) ? 'text-text bg-surface-2' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </nav>

                {{-- ── Header derecho: herramientas transversales (desktop) ── --}}
                <div class="hidden md:flex items-center gap-1">
                    @if ($torneosAccess)
                        {{-- 🔍 Buscar (panel desplegable) --}}
                        <div class="relative" @click.outside="searchOpen = false">
                            <button type="button" aria-label="Buscar"
                                    @click="searchOpen = !searchOpen; $nextTick(() => $refs.searchInput && $refs.searchInput.focus())"
                                    class="flex items-center justify-center w-9 h-9 rounded-full text-muted hover:text-text hover:bg-surface-2 transition-all duration-fast">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                            </button>
                            <div x-show="searchOpen" x-cloak x-transition
                                 class="absolute right-0 mt-1 w-80 bg-surface border border-border rounded-md shadow-card-2 p-3 z-50">
                                <form method="GET" action="{{ route('social.search') }}" class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                                        <input x-ref="searchInput" type="search" name="q" placeholder="Jugadores, clubes, torneos…"
                                               class="w-full pl-9 pr-3 py-2 rounded-md bg-bg border border-border text-text text-[13px] placeholder:text-muted focus:outline-none focus:border-green">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm shrink-0">Ir</button>
                                </form>
                            </div>
                        </div>

                        {{-- 🔔 Feed (novedades) --}}
                        <a href="{{ route('social.feed.index') }}" aria-label="Novedades"
                           class="relative flex items-center justify-center w-9 h-9 rounded-full transition-all duration-fast {{ $isActive('social.feed') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.66V5a2 2 0 10-4 0v.34A6 6 0 006 11v3.2c0 .53-.21 1.04-.59 1.41L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if (($feedUnreadCount ?? 0) > 0)
                                <span class="absolute top-0.5 right-0.5 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $feedUnreadCount > 9 ? '9+' : $feedUnreadCount }}</span>
                            @endif
                        </a>

                        {{-- 💬 Mensajes --}}
                        <a href="{{ route('social.conversaciones.index') }}" aria-label="Mensajes"
                           class="relative flex items-center justify-center w-9 h-9 rounded-full transition-all duration-fast {{ $isActive('social.conversaciones') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"/></svg>
                            @if (($messagesUnreadCount ?? 0) > 0)
                                <span class="absolute top-0.5 right-0.5 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $messagesUnreadCount > 9 ? '9+' : $messagesUnreadCount }}</span>
                            @endif
                        </a>
                    @endif

                    {{-- 👤 Avatar / Perfil (hub de cuenta e identidad) --}}
                    <div class="relative ml-1" @click.outside="profileOpen = false">
                        <button type="button" @click="profileOpen = !profileOpen"
                                class="flex items-center gap-2 rounded-pill pl-1 pr-2 py-1 hover:bg-surface-2 transition-all duration-fast {{ $profileActive ? 'bg-surface-2' : '' }}">
                            <x-avatar :user="$user" size="sm" />
                            <svg class="w-3.5 h-3.5 text-muted" :class="profileOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                            @if (($pendingClaimApprovals ?? 0) > 0)
                                <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-green border-2 border-bg"></span>
                            @endif
                        </button>

                        <div x-show="profileOpen" x-cloak x-transition
                             class="absolute right-0 mt-1 w-64 bg-surface border border-border rounded-md shadow-card-2 py-1 z-50">
                            {{-- Cabecera: avatar + nombre --}}
                            <div class="flex items-center gap-3 px-4 py-3 border-b border-border">
                                <x-avatar :user="$user" size="sm" />
                                <div class="min-w-0">
                                    <p class="font-display font-bold text-text text-[14px] truncate">{{ $user->name }}</p>
                                    <p class="font-mono text-[10px] text-muted truncate">{{ $user->futgo_id ?? $user->email }}</p>
                                </div>
                            </div>

                            @if ($torneosAccess)
                                <p class="px-4 pt-2 pb-1 font-mono text-[10px] uppercase tracking-wide-label text-muted">Mi perfil</p>
                                <a href="{{ route('torneos.mi-carrera') }}"
                                   class="block px-4 py-2 text-[14px] font-semibold {{ $isActive('torneos.mi-carrera') ? 'text-text bg-surface-2' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                                    Mi Carrera
                                </a>
                                <a href="{{ route('torneos.mis-equipos') }}"
                                   class="block px-4 py-2 text-[14px] font-semibold {{ $isActive('torneos.mis-equipos') ? 'text-text bg-surface-2' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                                    Mis Equipos
                                </a>
                            @endif

                            <div class="border-t border-border my-1"></div>

                            {{-- Tema claro/oscuro --}}
                            <button type="button" @click="$store.theme.toggle()"
                                    class="w-full flex items-center justify-between px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                <span x-text="$store.theme.isDark ? 'Tema claro' : 'Tema oscuro'"></span>
                                <svg x-show="$store.theme.isDark" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                                <svg x-show="!$store.theme.isDark" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41"/></svg>
                            </button>

                            {{-- Configurar perfil --}}
                            <a href="{{ route('profile.show') }}"
                               class="block px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                Configurar perfil
                            </a>

                            {{-- Reclamos de perfil (Limitación #2) --}}
                            <a href="{{ route('torneos.reclamos.index') }}"
                               class="block px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                Reclamar mi perfil
                            </a>
                            @if (($pendingClaimApprovals ?? 0) > 0)
                                <a href="{{ route('torneos.reclamos.approvals') }}"
                                   class="flex items-center justify-between px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                    <span>Reclamos por aprobar</span>
                                    <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-green text-white text-[11px] font-bold leading-5 text-center">{{ $pendingClaimApprovals > 9 ? '9+' : $pendingClaimApprovals }}</span>
                                </a>
                            @endif

                            {{-- Instalar PWA --}}
                            <button x-show="$store.pwa.canInstall" x-cloak @click="$store.pwa.install()"
                                    class="w-full flex items-center gap-2 px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2 text-left">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                                Instalar app
                            </button>

                            {{-- Salir --}}
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-border mt-1">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-[14px] font-semibold text-alerta hover:bg-surface-2">Salir</button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ── Mobile: iconos rápidos + hamburguesa ── --}}
                <div class="md:hidden flex items-center gap-0.5">
                    @if ($torneosAccess)
                        <a href="{{ route('social.search') }}" aria-label="Buscar" class="flex items-center justify-center w-9 h-9 rounded-full text-muted hover:text-text hover:bg-surface-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        </a>
                        <a href="{{ route('social.conversaciones.index') }}" aria-label="Mensajes" class="relative flex items-center justify-center w-9 h-9 rounded-full text-muted hover:text-text hover:bg-surface-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"/></svg>
                            @if (($messagesUnreadCount ?? 0) > 0)
                                <span class="absolute top-0.5 right-0.5 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $messagesUnreadCount > 9 ? '9+' : $messagesUnreadCount }}</span>
                            @endif
                        </a>
                    @endif
                    <button type="button" class="btn btn-icon btn-ghost btn-sm" @click="open = !open" aria-label="Menu">
                        <svg x-show="!open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg x-show="open" x-cloak fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- ════════════════ Mobile menu (v3, por secciones) ════════════════ --}}
            <div x-show="open" x-cloak class="md:hidden pb-3 space-y-1">
                @if ($torneosAccess)
                    {{-- Inicio + novedades --}}
                    <a href="{{ $homeRoute }}" class="flex items-center gap-2 px-3 py-2 rounded-sm font-semibold {{ $inicioActive ? 'text-text bg-surface-2' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-8 9 8M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
                        Inicio
                    </a>
                    <a href="{{ route('social.feed.index') }}" class="flex items-center justify-between px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                        <span>Novedades</span>
                        @if (($feedUnreadCount ?? 0) > 0)
                            <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-green text-white text-[11px] font-bold leading-5 text-center">{{ $feedUnreadCount > 9 ? '9+' : $feedUnreadCount }}</span>
                        @endif
                    </a>

                    {{-- Jugar --}}
                    <p class="px-3 pt-3 pb-1 font-mono text-[10px] uppercase tracking-wide-label text-muted">Jugar</p>
                    @foreach ($jugarItems as $item)
                        <a href="{{ route($item['route']) }}" class="block px-3 py-2 rounded-sm font-semibold {{ $isActive($item['starts']) ? 'text-text bg-surface-2' : 'text-muted hover:text-text hover:bg-surface-2' }}">{{ $item['label'] }}</a>
                    @endforeach

                    {{-- Competir --}}
                    <p class="px-3 pt-3 pb-1 font-mono text-[10px] uppercase tracking-wide-label text-muted">Competir</p>
                    @foreach ($competirItems as $item)
                        <a href="{{ route($item['route']) }}" class="block px-3 py-2 rounded-sm font-semibold {{ $isActive($item['starts']) ? 'text-text bg-surface-2' : 'text-muted hover:text-text hover:bg-surface-2' }}">{{ $item['label'] }}</a>
                    @endforeach

                    {{-- Comunidad --}}
                    <p class="px-3 pt-3 pb-1 font-mono text-[10px] uppercase tracking-wide-label text-muted">Comunidad</p>
                    @foreach ($comunidadItems as $item)
                        <a href="{{ route($item['route']) }}" class="block px-3 py-2 rounded-sm font-semibold {{ $isActive($item['starts']) ? 'text-text bg-surface-2' : 'text-muted hover:text-text hover:bg-surface-2' }}">{{ $item['label'] }}</a>
                    @endforeach
                @endif

                @if (! empty($pronosItems))
                    <p class="px-3 pt-3 pb-1 font-mono text-[10px] uppercase tracking-wide-label text-muted">Pronósticos</p>
                    @foreach ($pronosItems as $item)
                        <a href="{{ route($item['route']) }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">{{ $item['label'] }}</a>
                    @endforeach
                @endif

                {{-- Mi perfil --}}
                <div class="border-t border-border mt-3 pt-2">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <x-avatar :user="$user" size="sm" />
                        <span class="font-display font-bold text-text text-[14px] truncate">{{ $user->name }}</span>
                    </div>
                    @if ($torneosAccess)
                        <a href="{{ route('torneos.mi-carrera') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Mi Carrera</a>
                        <a href="{{ route('torneos.mis-equipos') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Mis Equipos</a>
                    @endif
                    <a href="{{ route('profile.show') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Configurar perfil</a>
                    <a href="{{ route('torneos.reclamos.index') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Reclamar mi perfil</a>
                    @if (($pendingClaimApprovals ?? 0) > 0)
                        <a href="{{ route('torneos.reclamos.approvals') }}" class="flex items-center justify-between px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                            <span>Reclamos por aprobar</span>
                            <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-green text-white text-[11px] font-bold leading-5 text-center">{{ $pendingClaimApprovals > 9 ? '9+' : $pendingClaimApprovals }}</span>
                        </a>
                    @endif

                    <button x-show="$store.pwa.canInstall" x-cloak @click="$store.pwa.install()"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2 text-left">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                        Instalar aplicación
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-sm font-semibold text-alerta hover:bg-surface-2">Salir</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
@else
    {{-- ════════════════════════ Guest nav ════════════════════════ --}}
    <nav class="sticky top-0 z-40 bg-bg/80 backdrop-blur-md border-b border-border" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">
                <a href="{{ route('home') }}">
                    <x-logo size="sm" />
                </a>

                {{-- Desktop: links inline --}}
                <div class="hidden sm:flex items-center gap-2">
                    <a href="{{ route('how-it-works') }}"
                       class="px-3.5 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $isActive('how-it-works') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                        ¿Cómo funciona?
                    </a>
                    <x-theme-toggle />
                    {{-- Instalar PWA (desktop guest) --}}
                    <button
                        x-show="$store.pwa.canInstall"
                        x-cloak
                        @click="$store.pwa.install()"
                        class="btn btn-secondary btn-sm flex items-center gap-1.5">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v12m0 0l-4-4m4 4l4-4"/>
                        </svg>
                        Instalar app
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-secondary btn-sm">Iniciar sesión</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Crear cuenta</a>
                </div>

                {{-- Mobile: hamburguesa --}}
                <div class="sm:hidden flex items-center gap-1">
                    <x-theme-toggle />
                    <button type="button" class="btn btn-icon btn-ghost btn-sm" @click="open = !open" aria-label="Menu">
                        <svg x-show="!open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="open" x-cloak fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mobile menu (guest) --}}
            <div x-show="open" x-cloak class="sm:hidden pb-3 space-y-1">
                <a href="{{ route('how-it-works') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">¿Cómo funciona?</a>
                <a href="{{ route('login') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Iniciar sesión</a>
                <a href="{{ route('register') }}" class="block px-3 py-2 rounded-sm font-semibold bg-green-tint text-green">Crear cuenta</a>
                {{-- Instalar PWA (mobile guest) --}}
                <button
                    x-show="$store.pwa.canInstall"
                    x-cloak
                    @click="$store.pwa.install()"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2 text-left">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v12m0 0l-4-4m4 4l4-4"/>
                    </svg>
                    Instalar aplicación
                </button>
            </div>
        </div>
    </nav>
@endif
