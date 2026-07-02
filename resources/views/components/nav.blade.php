@props([
    'user' => null,  // authenticated user model (null = guest)
])

@php
    $routeName = request()->route()?->getName() ?? '';
    $isActive = fn ($needle) => str_starts_with($routeName, $needle);
    $anyActive = fn (array $needles) => collect($needles)->contains(fn ($n) => str_starts_with($routeName, $n));

    // Todo usuario autenticado tiene acceso pleno a Torneos/Social.
    $torneosAccess   = (bool) $user;
    $isPlatformAdmin = $user?->isAdmin() ?? false;

    // Grupos de navegación
    $jugarItems = [];
    $competirItems = [];
    $comunidadItems = [];

    if ($torneosAccess) {
        $jugarItems = [
            ['route' => 'torneos.mis-equipos',        'label' => 'Mis Equipos',   'starts' => 'torneos.mis-equipos',  'desc' => 'Tus equipos en torneos activos'],
            ['route' => 'social.oportunidades.index', 'label' => 'Oportunidades', 'starts' => 'social.oportunidades', 'desc' => 'Busca o publica rival, jugadores o refuerzos'],
            ['route' => 'social.amistosos.index',     'label' => 'Amistosos',     'starts' => 'social.amistosos',     'desc' => 'Reporta resultados y resuelve disputas'],
            ['route' => 'social.oportunidades.express','label' => 'Modo rápido ⚡','starts' => 'social.oportunidades.express', 'desc' => '¿Necesitas rival para mañana?'],
            ['route' => 'social.agenda.index',        'label' => 'Agenda',        'starts' => 'social.agenda',        'desc' => 'Todo lo programado, en un solo lugar'],
        ];
        $competirItems = [
            ['route' => 'torneos.index',       'label' => 'Mis Torneos',   'starts' => 'torneos.index',  'desc' => 'Tus torneos en curso e históricos'],
            ['route' => 'torneos.public.index','label' => 'Buscar Torneo', 'starts' => 'torneos.public', 'desc' => 'Explora torneos abiertos a inscripción'],
            ['route' => 'torneos.ranking',     'label' => 'Ranking de la plataforma', 'starts' => 'torneos.ranking','desc' => 'Mejores jugadores de la plataforma'],
        ];
        $comunidadItems = [
            ['route' => 'social.canchas.index','label' => 'Canchas',                'starts' => 'social.canchas', 'desc' => 'Catálogo de canchas de la comunidad'],
            ['route' => 'social.search',       'label' => 'Buscar jugadores y clubes','starts' => 'social.search','desc' => 'Encuentra personas, equipos y torneos'],
        ];
    }

    $jugarActive     = $anyActive(['social.oportunidades', 'social.amistosos', 'social.agenda', 'torneos.mis-equipos']);
    $competirActive  = $anyActive(['torneos.index', 'torneos.public', 'torneos.ranking']);
    $comunidadActive = $anyActive(['social.canchas', 'social.search']);
    $inicioActive    = $isActive('dashboard');
    $profileActive   = $anyActive(['torneos.mi-carrera', 'torneos.mis-equipos', 'profile.', 'torneos.reclamos']);

    $homeRoute = $torneosAccess ? route('dashboard') : route('profile.show');

    // ── Pestañas de la bottom nav (mobile). Sin FAB: 4 dominios reales. ──
    $bottomTabs = [];
    $bottomTabs[] = ['key' => 'inicio', 'type' => 'link', 'href' => $homeRoute, 'label' => 'Inicio', 'active' => $inicioActive, 'icon' => 'home'];
    if ($torneosAccess) {
        $bottomTabs[] = ['key' => 'competir',  'type' => 'sheet', 'label' => 'Competir',  'active' => $competirActive,  'icon' => 'trophy'];
        $bottomTabs[] = ['key' => 'jugar',     'type' => 'sheet', 'label' => 'Jugar',     'active' => $jugarActive,     'icon' => 'ball'];
        $bottomTabs[] = ['key' => 'comunidad', 'type' => 'sheet', 'label' => 'Comunidad', 'active' => $comunidadActive, 'icon' => 'users'];
    }
    $bottomTabs[] = ['key' => 'yo', 'type' => 'sheet', 'label' => 'Yo', 'active' => $profileActive, 'icon' => 'avatar'];

    // Sheets de tipo "lista de enlaces" (Jugar / Competir / Comunidad).
    $listSheets = [];
    if ($torneosAccess) {
        $listSheets['competir']  = ['title' => 'Competir',  'items' => $competirItems];
        $listSheets['jugar']     = ['title' => 'Jugar',     'items' => $jugarItems];
        $listSheets['comunidad'] = ['title' => 'Comunidad', 'items' => $comunidadItems];
    }

    $hasBottomNav = $torneosAccess;
@endphp

@if ($user)
    {{-- ════════════════════════ Auth: barra superior ════════════════════════ --}}
    <nav class="sticky top-0 z-40 bg-bg/80 backdrop-blur-md border-b border-border"
         style="padding-top: env(safe-area-inset-top);"
         x-data="{ profileOpen: false, searchOpen: false, jugarOpen: false, competirOpen: false, comunidadOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-3">

                {{-- Brand --}}
                <a href="{{ $homeRoute }}" class="shrink-0">
                    <x-logo size="sm" />
                </a>

                {{-- ── Nav principal (desktop): 4 dominios ── --}}
                <nav class="hidden md:flex items-center gap-1">
                    @if ($torneosAccess)
                        <a href="{{ $homeRoute }}"
                           class="flex items-center gap-1.5 px-3 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $inicioActive ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-8 9 8M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
                            Inicio
                        </a>

                        <x-nav-dropdown label="Competir" :active="$competirActive" state="competirOpen" :items="$competirItems" :is-active="$isActive">
                            <x-slot:icon>
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM7 6H4v1a3 3 0 003 3M17 6h3v1a3 3 0 01-3 3"/></svg>
                            </x-slot:icon>
                        </x-nav-dropdown>

                        <x-nav-dropdown label="Jugar" :active="$jugarActive" state="jugarOpen" :items="$jugarItems" :is-active="$isActive">
                            <x-slot:icon>
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.5 4.5L19 8l-3 3.5L17 16l-5-2-5 2 1-4.5L5 8l4.5-.5z"/></svg>
                            </x-slot:icon>
                        </x-nav-dropdown>

                        <x-nav-dropdown label="Comunidad" :active="$comunidadActive" state="comunidadOpen" :items="$comunidadItems" :is-active="$isActive">
                            <x-slot:icon>
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-4-4M9 20H4v-1a4 4 0 014-4h2a4 4 0 014 4v1H9zm6-9a3 3 0 100-6 3 3 0 000 6zm-6 0a3 3 0 100-6 3 3 0 000 6z"/></svg>
                            </x-slot:icon>
                        </x-nav-dropdown>
                    @endif
                </nav>

                {{-- ── Header derecho: herramientas transversales (desktop) ── --}}
                <div class="hidden md:flex items-center gap-1">
                    @if ($torneosAccess)
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

                        <a href="{{ route('social.feed.index') }}" aria-label="Novedades"
                           class="relative flex items-center justify-center w-9 h-9 rounded-full transition-all duration-fast {{ $isActive('social.feed') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.66V5a2 2 0 10-4 0v.34A6 6 0 006 11v3.2c0 .53-.21 1.04-.59 1.41L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if (($feedUnreadCount ?? 0) > 0)
                                <span class="absolute top-0.5 right-0.5 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $feedUnreadCount > 9 ? '9+' : $feedUnreadCount }}</span>
                            @endif
                        </a>

                        <a href="{{ route('social.conversaciones.index') }}" aria-label="Mensajes"
                           class="relative flex items-center justify-center w-9 h-9 rounded-full transition-all duration-fast {{ $isActive('social.conversaciones') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"/></svg>
                            @if (($messagesUnreadCount ?? 0) > 0)
                                <span class="absolute top-0.5 right-0.5 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $messagesUnreadCount > 9 ? '9+' : $messagesUnreadCount }}</span>
                            @endif
                        </a>
                    @endif

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
                            @endif

                            <div class="border-t border-border my-1"></div>

                            <button type="button" @click="$store.theme.toggle()"
                                    class="w-full flex items-center justify-between px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                <span x-text="$store.theme.isDark ? 'Tema claro' : 'Tema oscuro'"></span>
                                <svg x-show="$store.theme.isDark" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                                <svg x-show="!$store.theme.isDark" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41"/></svg>
                            </button>

                            <a href="{{ route('profile.show') }}"
                               class="block px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                Configurar perfil
                            </a>

                            <a href="{{ route('privacidad.centro') }}"
                               class="block px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                Centro de Privacidad
                            </a>

                            <a href="{{ route('soporte.index') }}"
                               class="block px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                Centro de Soporte
                            </a>

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

                            @if ($isPlatformAdmin)
                                <a href="{{ route('admin.dashboard') }}"
                                   class="block px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2">
                                    Panel Admin
                                </a>
                            @endif

                            <button x-show="$store.pwa.canInstall" x-cloak @click="$store.pwa.install()"
                                    class="w-full flex items-center gap-2 px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2 text-left">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                                Instalar app
                            </button>

                            <a href="{{ route('privacidad.eliminar') }}"
                               class="block px-4 py-2 text-[14px] font-semibold text-muted hover:text-text hover:bg-surface-2 border-t border-border mt-1">
                                Eliminar mi cuenta
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-[14px] font-semibold text-alerta hover:bg-surface-2">Salir</button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ── Mobile: solo herramientas transversales (sin menú de navegación) ── --}}
                <div class="md:hidden flex items-center gap-0.5">
                    @if ($torneosAccess)
                        <a href="{{ route('social.search') }}" aria-label="Buscar"
                           class="flex items-center justify-center w-11 h-11 rounded-full text-muted hover:text-text hover:bg-surface-2">
                            <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        </a>
                        <a href="{{ route('social.feed.index') }}" aria-label="Novedades"
                           class="relative flex items-center justify-center w-11 h-11 rounded-full {{ $isActive('social.feed') ? 'text-green' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.66V5a2 2 0 10-4 0v.34A6 6 0 006 11v3.2c0 .53-.21 1.04-.59 1.41L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if (($feedUnreadCount ?? 0) > 0)
                                <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $feedUnreadCount > 9 ? '9+' : $feedUnreadCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('social.conversaciones.index') }}" aria-label="Mensajes"
                           class="relative flex items-center justify-center w-11 h-11 rounded-full {{ $isActive('social.conversaciones') ? 'text-green' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"/></svg>
                            @if (($messagesUnreadCount ?? 0) > 0)
                                <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $messagesUnreadCount > 9 ? '9+' : $messagesUnreadCount }}</span>
                            @endif
                        </a>
                    @else
                        <button type="button" @click="$store.theme.toggle()" aria-label="Cambiar tema"
                                class="flex items-center justify-center w-11 h-11 rounded-full text-muted hover:text-text hover:bg-surface-2">
                            <svg x-show="$store.theme.isDark" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-[22px] h-[22px]"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                            <svg x-show="!$store.theme.isDark" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-[22px] h-[22px]"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41"/></svg>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    {{-- ════════ Mobile: Bottom Nav Bar + bottom sheets ════════
         Va FUERA del <nav> con backdrop-blur: un ancestro con backdrop-filter
         crea un containing block para position:fixed, lo que rompería el anclaje
         al viewport. Como hijo directo de <body> (sin transform/filter), los
         elementos fixed se anclan correctamente al borde inferior de la pantalla. --}}
    @if ($hasBottomNav)
        <div class="md:hidden" x-data="{ sheet: null }" @keydown.escape.window="sheet = null">

            {{-- Overlay --}}
            <div x-show="sheet !== null" x-cloak
                 @click="sheet = null"
                 class="fixed inset-0 z-[45] bg-black/50"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
            </div>

            {{-- Bottom sheets de lista (Jugar / Competir / Comunidad / Pronósticos) --}}
            @foreach ($listSheets as $key => $sheet)
                <div x-show="sheet === '{{ $key }}'" x-cloak
                     class="fixed inset-x-0 bottom-0 z-50 bg-surface rounded-t-2xl border-t border-border shadow-modal max-h-[80vh] overflow-y-auto"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="translate-y-full"
                     x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="translate-y-0"
                     x-transition:leave-end="translate-y-full">
                    <div class="flex justify-center pt-3 pb-1">
                        <div class="w-10 h-1.5 rounded-full bg-border"></div>
                    </div>
                    <p class="px-5 pt-2 pb-2 font-mono text-[11px] uppercase tracking-wide-label text-muted">{{ $sheet['title'] }}</p>
                    <div class="px-3 pb-[calc(env(safe-area-inset-bottom)+1.25rem)] space-y-0.5">
                        @foreach ($sheet['items'] as $item)
                            <a href="{{ route($item['route']) }}" @click="sheet = null"
                               class="flex items-start gap-3 px-4 py-3.5 rounded-xl active:bg-surface-2 {{ $isActive($item['starts']) ? 'bg-surface-2' : '' }}">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-[15px] text-text leading-snug">{{ $item['label'] }}</p>
                                    @isset($item['desc'])
                                        <p class="text-[12.5px] text-muted mt-0.5 leading-snug">{{ $item['desc'] }}</p>
                                    @endisset
                                </div>
                                @if ($isActive($item['starts']))
                                    <svg class="w-5 h-5 text-green shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </a>
                        @endforeach

                        @if (! empty($sheet['extra']))
                            <div class="border-t border-border mt-2 pt-2">
                                @foreach ($sheet['extra'] as $item)
                                    <a href="{{ route($item['route']) }}" @click="sheet = null"
                                       class="flex items-center px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2 {{ $isActive($item['starts']) ? 'bg-surface-2' : '' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Bottom sheet: Yo (perfil / cuenta) --}}
            <div x-show="sheet === 'yo'" x-cloak
                 class="fixed inset-x-0 bottom-0 z-50 bg-surface rounded-t-2xl border-t border-border shadow-modal max-h-[85vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full">
                <div class="flex justify-center pt-3 pb-1">
                    <div class="w-10 h-1.5 rounded-full bg-border"></div>
                </div>
                <div class="flex items-center gap-3 px-5 py-3 border-b border-border">
                    <x-avatar :user="$user" size="md" />
                    <div class="min-w-0">
                        <p class="font-display font-bold text-text text-[16px] truncate">{{ $user->name }}</p>
                        <p class="font-mono text-[11px] text-muted truncate">{{ $user->futgo_id ?? $user->email }}</p>
                    </div>
                </div>
                <div class="px-3 py-3 pb-[calc(env(safe-area-inset-bottom)+1rem)] space-y-0.5">
                    @if ($torneosAccess)
                        <a href="{{ route('torneos.mi-carrera') }}" @click="sheet = null"
                           class="flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] active:bg-surface-2 {{ $isActive('torneos.mi-carrera') ? 'bg-surface-2 text-text' : 'text-text' }}">
                            <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Mi Carrera
                        </a>
                    @endif
                    <a href="{{ route('profile.show') }}" @click="sheet = null"
                       class="flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2">
                        <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                        Configurar perfil
                    </a>
                    <a href="{{ route('privacidad.centro') }}" @click="sheet = null"
                       class="flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2">
                        <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Centro de Privacidad
                    </a>
                    <a href="{{ route('soporte.index') }}" @click="sheet = null"
                       class="flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2">
                        <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.6 5.6l3.1 3.1m6.6 6.6l3.1 3.1m0-12.8l-3.1 3.1m-6.6 6.6l-3.1 3.1"/></svg>
                        Centro de Soporte
                    </a>
                    @if ($torneosAccess)
                        <a href="{{ route('torneos.reclamos.index') }}" @click="sheet = null"
                           class="flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2">
                            <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Reclamar mi perfil
                            @if (($pendingClaimApprovals ?? 0) > 0)
                                <span class="ml-auto min-w-[1.25rem] h-5 px-1.5 rounded-full bg-green text-white text-[11px] font-bold leading-5 text-center">{{ $pendingClaimApprovals > 9 ? '9+' : $pendingClaimApprovals }}</span>
                            @endif
                        </a>
                    @endif

                    <div class="border-t border-border mt-1 pt-1">
                        <button type="button" @click="$store.theme.toggle()"
                                class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2 text-left">
                            <svg x-show="$store.theme.isDark" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-muted"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                            <svg x-show="!$store.theme.isDark" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-muted"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41"/></svg>
                            <span x-text="$store.theme.isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"></span>
                        </button>
                        <button x-show="$store.pwa.canInstall" x-cloak @click="$store.pwa.install()"
                                class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2 text-left">
                            <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                            Instalar FutGO
                        </button>
                        @if ($isPlatformAdmin)
                            <a href="{{ route('admin.dashboard') }}" @click="sheet = null"
                               class="flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2">
                                <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><circle cx="12" cy="12" r="3"/></svg>
                                Panel Admin
                            </a>
                        @endif

                        <a href="{{ route('privacidad.eliminar') }}" @click="sheet = null"
                           class="flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-text active:bg-surface-2">
                            <svg class="w-5 h-5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Eliminar mi cuenta
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl font-semibold text-[15px] text-alerta active:bg-surface-2 text-left">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Salir
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ── La barra inferior fija ── --}}
            <div class="fixed bottom-0 inset-x-0 z-40 bg-bg/95 backdrop-blur-md border-t border-border"
                 style="padding-bottom: env(safe-area-inset-bottom);">
                <div class="grid h-16" style="grid-template-columns: repeat({{ count($bottomTabs) }}, minmax(0, 1fr));">
                    @foreach ($bottomTabs as $tab)
                        @php $activeCls = $tab['active'] ? 'text-green' : 'text-muted'; @endphp
                        @if ($tab['type'] === 'link')
                            <a href="{{ $tab['href'] }}"
                               class="flex flex-col items-center justify-center gap-1 {{ $activeCls }} active:bg-surface-2">
                                @include('components.nav-bottom-icon', ['icon' => $tab['icon'], 'user' => $user, 'pending' => $pendingClaimApprovals ?? 0])
                                <span class="text-[10px] font-semibold leading-none">{{ $tab['label'] }}</span>
                            </a>
                        @else
                            <button type="button" @click="sheet = (sheet === '{{ $tab['key'] }}' ? null : '{{ $tab['key'] }}')"
                                    class="flex flex-col items-center justify-center gap-1 {{ $activeCls }} active:bg-surface-2"
                                    :class="sheet === '{{ $tab['key'] }}' ? 'text-green' : ''">
                                @include('components.nav-bottom-icon', ['icon' => $tab['icon'], 'user' => $user, 'pending' => $pendingClaimApprovals ?? 0])
                                <span class="text-[10px] font-semibold leading-none">{{ $tab['label'] }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    {{-- /Bottom Nav --}}
@else
    {{-- ════════════════════════ Guest nav ════════════════════════ --}}
    <nav class="sticky top-0 z-40 bg-bg/80 backdrop-blur-md border-b border-border" style="padding-top: env(safe-area-inset-top);" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">
                <a href="{{ route('home') }}">
                    <x-logo size="sm" />
                </a>

                {{-- Desktop: links inline --}}
                <div class="hidden sm:flex items-center gap-2">
                    <a href="{{ route('torneos.public.index') }}"
                       class="px-3.5 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $isActive('torneos.public') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                        Torneos
                    </a>
                    <a href="{{ route('social.oportunidades.index') }}"
                       class="px-3.5 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $isActive('social.oportunidades') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                        Oportunidades
                    </a>
                    <x-theme-toggle />
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

                {{-- Mobile: hamburguesa (solo para guest) --}}
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
                <a href="{{ route('torneos.public.index') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Torneos</a>
                <a href="{{ route('social.oportunidades.index') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Oportunidades</a>
                <a href="{{ route('login') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Iniciar sesión</a>
                <a href="{{ route('register') }}" class="block px-3 py-2 rounded-sm font-semibold bg-green-tint text-green">Crear cuenta</a>
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
