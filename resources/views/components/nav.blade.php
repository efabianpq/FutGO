@props([
    'user' => null,  // authenticated user model (null = guest)
])

@php
    $routeName = request()->route()?->getName() ?? '';
    $isActive = fn ($needle) => str_starts_with($routeName, $needle);
@endphp

@if ($user)
    {{-- Auth nav --}}
    <nav class="bg-pitch text-bone shadow-card-2" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-6">

                {{-- Brand --}}
                <a href="{{ route('predictions.index') }}" class="font-display font-extrabold text-[20px] uppercase tracking-[.04em] shrink-0">
                    <span class="text-gol">@</span>SoyPachon
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden md:flex items-center gap-6 font-display font-semibold text-[13px] uppercase tracking-wide-label">
                    @foreach ([
                        'predictions.index' => ['label' => 'Mis Pronósticos', 'icon' => null,  'starts' => 'predictions'],
                        'how-it-works'      => ['label' => '¿Cómo funciona?', 'icon' => null, 'starts' => 'how-it-works'],
                        'ranking.index'     => ['label' => 'Ranking',          'icon' => null, 'starts' => 'ranking'],
                        'audit.index'       => ['label' => 'Auditoría',        'icon' => '↓',  'starts' => 'audit'],
                        'profile.show'      => ['label' => 'Perfil',           'icon' => null, 'starts' => 'profile'],
                    ] as $route => $meta)
                        <a href="{{ route($route) }}"
                           class="pb-1 border-b-2 transition-all duration-fast {{ $isActive($meta['starts']) ? 'border-gol opacity-100' : 'border-transparent opacity-70 hover:opacity-100' }}">
                            @if ($meta['icon']) <span class="text-gol">{{ $meta['icon'] }}</span> @endif{{ $meta['label'] }}
                        </a>
                    @endforeach
                </nav>

                {{-- Right side --}}
                <div class="hidden md:flex items-center gap-3">
                    <span class="font-mono text-[11px] tracking-[.12em] uppercase px-3 py-1.5 bg-bone/10 rounded-pill">
                        {{ explode(' ', $user->name)[0] }}
                    </span>
                    @if ($user->isAdmin())
                        <a href="{{ route('admin.dashboard') }}"
                           class="font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-gol text-pitch hover:bg-gol-deep transition-all duration-fast">
                            ⚙ Admin
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-pitch-deep hover:bg-black/20 transition-all duration-fast">
                            Salir
                        </button>
                    </form>
                </div>

                {{-- Mobile hamburguesa --}}
                <button type="button" class="md:hidden p-2 rounded-md hover:bg-bone/10" @click="open = !open" aria-label="Menu">
                    <svg x-show="!open" class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" x-cloak class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mobile menu --}}
            <div x-show="open" x-cloak class="md:hidden pb-3 space-y-1 font-display font-semibold text-[13px] uppercase tracking-wide-label">
                <a href="{{ route('predictions.index') }}" class="block px-3 py-2 rounded-md hover:bg-bone/10">Mis Pronósticos</a>
                <a href="{{ route('how-it-works') }}" class="block px-3 py-2 rounded-md hover:bg-bone/10">¿Cómo funciona?</a>
                <a href="{{ route('ranking.index') }}" class="block px-3 py-2 rounded-md hover:bg-bone/10">Ranking</a>
                <a href="{{ route('audit.index') }}" class="block px-3 py-2 rounded-md hover:bg-bone/10"><span class="text-gol">↓</span> Auditoría</a>
                <a href="{{ route('profile.show') }}" class="block px-3 py-2 rounded-md hover:bg-bone/10">Perfil</a>
                @if ($user->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded-md bg-gol text-pitch">⚙ Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-3 py-2 rounded-md hover:bg-bone/10">Salir</button>
                </form>
            </div>
        </div>
    </nav>
@else
    {{-- Guest nav (con hamburguesa en mobile para evitar overflow) --}}
    <nav class="bg-pitch text-bone shadow-card-2" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14 gap-4">
                <a href="{{ route('home') }}" class="font-display font-extrabold text-[18px] uppercase tracking-[.04em]">
                    <span class="text-gol">@</span>SoyPachon
                </a>

                {{-- Desktop: links inline --}}
                <div class="hidden sm:flex items-center gap-2 font-display font-semibold text-[13px] uppercase tracking-wide-label">
                    <a href="{{ route('how-it-works') }}"
                       class="px-3 py-1.5 rounded-md transition-all duration-fast {{ $isActive('how-it-works') ? 'bg-gol/20 text-gol' : 'hover:bg-bone/10' }}">
                        ¿Cómo funciona?
                    </a>
                    <a href="{{ route('login') }}" class="px-3.5 py-2 rounded-md bg-pitch-deep hover:bg-black/20 transition-all duration-fast">Iniciar sesión</a>
                    <a href="{{ route('register') }}" class="px-3.5 py-2 rounded-md bg-gol text-pitch hover:bg-gol-deep transition-all duration-fast">Crear cuenta</a>
                </div>

                {{-- Mobile: hamburguesa --}}
                <button type="button" class="sm:hidden p-2 rounded-md hover:bg-bone/10" @click="open = !open" aria-label="Menu">
                    <svg x-show="!open" class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" x-cloak class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mobile menu (guest) --}}
            <div x-show="open" x-cloak class="sm:hidden pb-3 space-y-1 font-display font-semibold text-[13px] uppercase tracking-wide-label">
                <a href="{{ route('how-it-works') }}" class="block px-3 py-2 rounded-md hover:bg-bone/10">¿Cómo funciona?</a>
                <a href="{{ route('login') }}" class="block px-3 py-2 rounded-md hover:bg-bone/10">Iniciar sesión</a>
                <a href="{{ route('register') }}" class="block px-3 py-2 rounded-md bg-gol text-pitch">Crear cuenta</a>
            </div>
        </div>
    </nav>
@endif
