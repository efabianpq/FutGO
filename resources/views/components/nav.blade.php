@props([
    'user' => null,  // authenticated user model (null = guest)
])

@php
    $routeName = request()->route()?->getName() ?? '';
    $isActive = fn ($needle) => str_starts_with($routeName, $needle);

    // Navegación modular: solo se muestran enlaces de módulos habilitados.
    // Cada enlace apunta a rutas siempre accesibles para el usuario (sin {param}
    // contextual) para no generar 403/302/páginas inaccesibles desde el navbar.
    $pollaAccess   = $user?->hasPollaAccess() ?? false;
    $torneosAccess = $user?->hasTorneosAccess() ?? false;

    // Roles contextuales del módulo Torneos (solo se consultan si hay acceso).
    $isTorneoAdmin = $torneosAccess && ($user?->isTorneoAdmin() ?? false);
    $isCaptain     = $torneosAccess && ($user?->isCaptainAnywhere() ?? false);
    $isTorneoPlayer = $torneosAccess && ($user?->isTorneoPlayerAnywhere() ?? false);

    // ── Nuevo orden del nav (H17): Mi Carrera · Mis Equipos · Mis Torneos ·
    //    Ranking · Pronósticos · Auditoría · Perfil | Admin (plataforma)
    $navLinks = [];

    if ($torneosAccess) {
        $navLinks[] = ['route' => 'torneos.mi-carrera',   'label' => 'Mi Carrera',  'starts' => 'torneos.mi-carrera'];
        $navLinks[] = ['route' => 'torneos.mis-equipos',  'label' => 'Mis Equipos', 'starts' => 'torneos.mis-equipos'];
        // "Torneos": portal de exploración de torneos públicos (H9).
        $navLinks[] = ['route' => 'torneos.public.index', 'label' => 'Torneos',     'starts' => 'torneos.public'];
        $navLinks[] = ['route' => 'torneos.index',        'label' => 'Mis Torneos', 'starts' => 'torneos.index'];
        $navLinks[] = ['route' => 'torneos.ranking',      'label' => 'Ranking',     'starts' => 'torneos.ranking'];
    }

    if ($pollaAccess) {
        $navLinks[] = ['route' => 'predictions.index', 'label' => 'Pronósticos', 'starts' => 'predictions'];
        $navLinks[] = ['route' => 'audit.index',       'label' => 'Auditoría',   'starts' => 'audit'];
    }

    $navLinks[] = ['route' => 'profile.show', 'label' => 'Perfil', 'starts' => 'profile'];

    $homeRoute = route('inicio');

    $homeRoute = route('inicio');
@endphp

@if ($user)
    {{-- Auth nav --}}
    <nav class="sticky top-0 z-40 bg-bg/80 backdrop-blur-md border-b border-border" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-6">

                {{-- Brand --}}
                <a href="{{ $homeRoute }}" class="shrink-0">
                    <x-logo size="sm" />
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden md:flex items-center gap-1">
                    @foreach ($navLinks as $meta)
                        <a href="{{ route($meta['route']) }}"
                           class="px-3.5 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $isActive($meta['starts']) ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
                            {{ $meta['label'] }}
                        </a>
                    @endforeach
                </nav>

                {{-- Right side --}}
                <div class="hidden md:flex items-center gap-2">
                    <x-theme-toggle />
                    <span class="font-mono text-[11px] tracking-[.12em] uppercase px-3 py-1.5 bg-surface-2 border border-border text-muted rounded-pill">
                        {{ explode(' ', $user->name)[0] }}
                    </span>
                    @if ($user->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-primary btn-sm">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">Salir</button>
                    </form>
                </div>

                {{-- Mobile hamburguesa --}}
                <div class="md:hidden flex items-center gap-1">
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

            {{-- Mobile menu --}}
            <div x-show="open" x-cloak class="md:hidden pb-3 space-y-1">
                @foreach ($navLinks as $meta)
                    <a href="{{ route($meta['route']) }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">
                        {{ $meta['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('how-it-works') }}" class="block px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">¿Cómo funciona?</a>
                @if ($user->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded-sm font-semibold bg-green-tint text-green">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-3 py-2 rounded-sm font-semibold text-muted hover:text-text hover:bg-surface-2">Salir</button>
                </form>
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
            </div>
        </div>
    </nav>
@endif
