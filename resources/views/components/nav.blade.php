@props([
    'user' => null,  // authenticated user model (null = guest)
])

@php
    $routeName = request()->route()?->getName() ?? '';
    $isActive = fn ($needle) => str_starts_with($routeName, $needle);

    // Navegación modular: solo se muestran enlaces de módulos habilitados.
    $pollaAccess   = $user?->hasPollaAccess() ?? false;
    $torneosAccess = $user?->hasTorneosAccess() ?? false;
    $isPlatformAdmin = $user?->isAdmin() ?? false;

    // ── Orden del nav (v2.0): Mi Carrera · Mis Equipos · Mis Torneos ·
    //    Buscar Torneo · Ranking de la plataforma · Pronósticos ▾  | Perfil ▾
    $navLinks = [];

    if ($torneosAccess) {
        $navLinks[] = ['route' => 'torneos.mi-carrera',   'label' => 'Mi Carrera',            'starts' => 'torneos.mi-carrera'];
        $navLinks[] = ['route' => 'social.agenda.index',  'label' => 'Agenda',                'starts' => 'social.agenda'];
        $navLinks[] = ['route' => 'torneos.mis-equipos',  'label' => 'Mis Equipos',           'starts' => 'torneos.mis-equipos'];
        $navLinks[] = ['route' => 'torneos.index',        'label' => 'Mis Torneos',           'starts' => 'torneos.index'];
        $navLinks[] = ['route' => 'social.oportunidades.index', 'label' => 'Oportunidades',   'starts' => 'social.oportunidades'];
        $navLinks[] = ['route' => 'social.amistosos.index', 'label' => 'Amistosos',           'starts' => 'social.amistosos'];
        $navLinks[] = ['route' => 'social.canchas.index',  'label' => 'Canchas',              'starts' => 'social.canchas'];
        $navLinks[] = ['route' => 'torneos.public.index', 'label' => 'Buscar Torneo',         'starts' => 'torneos.public'];
        $navLinks[] = ['route' => 'torneos.ranking',      'label' => 'Ranking de la plataforma', 'starts' => 'torneos.ranking'];
    }

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
    $pronosActive = $isActive('predictions') || $isActive('audit') || $isActive('how-it-works') || $isActive('admin.dashboard');

    // Destino del logo: Mi Carrera para usuarios de torneos; degrada con gracia.
    if ($torneosAccess) {
        $homeRoute = route('torneos.mi-carrera');
    } elseif ($pollaAccess) {
        $homeRoute = route('predictions.index');
    } else {
        $homeRoute = route('profile.show');
    }
@endphp

@if ($user)
    {{-- Auth nav --}}
    <nav class="sticky top-0 z-40 bg-bg/80 backdrop-blur-md border-b border-border"
         x-data="{ open: false, profileOpen: false, pronosOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">

                {{-- Brand --}}
                <a href="{{ $homeRoute }}" class="shrink-0">
                    <x-logo size="sm" />
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden md:flex items-center gap-1">
                    @foreach ($navLinks as $meta)
                        <a href="{{ route($meta['route']) }}"
                           class="px-3 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $isActive($meta['starts']) ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            {{ $meta['label'] }}
                        </a>
                    @endforeach

                    {{-- Feed de FutGO Social con badge de no leídos --}}
                    @if ($torneosAccess)
                        <a href="{{ route('social.feed.index') }}"
                           class="relative px-3 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $isActive('social.feed') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            Feed
                            @if (($feedUnreadCount ?? 0) > 0)
                                <span class="absolute top-0.5 right-0 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $feedUnreadCount > 9 ? '9+' : $feedUnreadCount }}</span>
                            @endif
                        </a>

                        {{-- Mensajes (conversaciones) con badge de no leídos --}}
                        <a href="{{ route('social.conversaciones.index') }}"
                           class="relative px-3 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $isActive('social.conversaciones') ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            Mensajes
                            @if (($messagesUnreadCount ?? 0) > 0)
                                <span class="absolute top-0.5 right-0 min-w-[1rem] h-4 px-1 rounded-full bg-green text-white text-[10px] font-bold leading-4 text-center">{{ $messagesUnreadCount > 9 ? '9+' : $messagesUnreadCount }}</span>
                            @endif
                        </a>
                    @endif

                    {{-- Dropdown Pronósticos (polla + admin) --}}
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

                {{-- Right side: botón instalar PWA (desktop auth) + menú de Perfil --}}
                <div class="hidden md:flex items-center gap-2">
                    <button
                        x-show="$store.pwa.canInstall"
                        x-cloak
                        @click="$store.pwa.install()"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-xs text-[13px] font-semibold text-muted hover:text-text hover:bg-surface-2 transition-all duration-fast">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v12m0 0l-4-4m4 4l4-4"/>
                        </svg>
                        Instalar app
                    </button>
                    <div class="relative" @click.outside="profileOpen = false">
                        <button type="button" @click="profileOpen = !profileOpen"
                                class="flex items-center gap-2 rounded-pill pl-1 pr-2 py-1 hover:bg-surface-2 transition-all duration-fast">
                            <x-avatar :user="$user" size="sm" />
                            <svg class="w-3.5 h-3.5 text-muted" :class="profileOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div x-show="profileOpen" x-cloak x-transition
                             class="absolute right-0 mt-1 w-60 bg-surface border border-border rounded-md shadow-card-2 py-1 z-50">
                            {{-- Cabecera: avatar + nombre --}}
                            <div class="flex items-center gap-3 px-4 py-3 border-b border-border">
                                <x-avatar :user="$user" size="sm" />
                                <div class="min-w-0">
                                    <p class="font-display font-bold text-text text-[14px] truncate">{{ $user->name }}</p>
                                    <p class="font-mono text-[10px] text-muted truncate">{{ $user->email }}</p>
                                </div>
                            </div>

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

                            {{-- Salir --}}
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-border mt-1">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-[14px] font-semibold text-alerta hover:bg-surface-2">Salir</button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Mobile hamburguesa --}}
                <div class="md:hidden flex items-center gap-1">
                    <button type="button" @click="$store.theme.toggle()" class="btn btn-icon btn-ghost btn-sm" aria-label="Tema">
                        <svg x-show="$store.theme.isDark" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                        <svg x-show="!$store.theme.isDark" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41"/></svg>
                    </button>
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

            {{-- Mobile menu --}}
            <div x-show="open" x-cloak class="md:hidden pb-3 space-y-1">
                @foreach ($navLinks as $meta)
                    <a href="{{ route($meta['route']) }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                        {{ $meta['label'] }}
                    </a>
                @endforeach

                @if ($torneosAccess)
                    <a href="{{ route('social.feed.index') }}" class="flex items-center justify-between px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                        <span>Feed</span>
                        @if (($feedUnreadCount ?? 0) > 0)
                            <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-green text-white text-[11px] font-bold leading-5 text-center">{{ $feedUnreadCount > 9 ? '9+' : $feedUnreadCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('social.conversaciones.index') }}" class="flex items-center justify-between px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                        <span>Mensajes</span>
                        @if (($messagesUnreadCount ?? 0) > 0)
                            <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-green text-white text-[11px] font-bold leading-5 text-center">{{ $messagesUnreadCount > 9 ? '9+' : $messagesUnreadCount }}</span>
                        @endif
                    </a>
                @endif

                @if (! empty($pronosItems))
                    <p class="px-3 pt-2 pb-1 font-mono text-[10px] uppercase tracking-wide-label text-muted">Pronósticos</p>
                    @foreach ($pronosItems as $item)
                        <a href="{{ route($item['route']) }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif

                {{-- Instalar PWA (mobile auth) --}}
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

                <div class="border-t border-border mt-2 pt-2">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <x-avatar :user="$user" size="sm" />
                        <span class="font-display font-bold text-text text-[14px] truncate">{{ $user->name }}</span>
                    </div>
                    <a href="{{ route('profile.show') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Configurar perfil</a>
                    <a href="{{ route('torneos.reclamos.index') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Reclamar mi perfil</a>
                    @if (($pendingClaimApprovals ?? 0) > 0)
                        <a href="{{ route('torneos.reclamos.approvals') }}" class="flex items-center justify-between px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                            <span>Reclamos por aprobar</span>
                            <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-green text-white text-[11px] font-bold leading-5 text-center">{{ $pendingClaimApprovals > 9 ? '9+' : $pendingClaimApprovals }}</span>
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-sm font-semibold text-alerta hover:bg-surface-2">Salir</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
@else
    {{-- Guest nav (con hamburguesa en mobile para evitar overflow) --}}
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
