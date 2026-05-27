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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 min-h-screen flex flex-col">

    @guest
        <nav class="bg-pachon-green text-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-14">
                <a href="{{ route('home') }}" class="font-bold flex items-center gap-2">
                    <span>⚽</span><span>Soy Pachón Mundial</span>
                </a>
                <div class="flex items-center gap-2 text-sm">
                    <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-md bg-pachon-green-dark hover:bg-black/20">Iniciar sesión</a>
                    <a href="{{ route('register') }}" class="px-3 py-1.5 rounded-md hover:bg-white/10">Crear cuenta</a>
                </div>
            </div>
        </nav>
    @endguest

    @auth
        <nav class="bg-pachon-green text-white shadow" x-data="{ open: false }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <a href="{{ route('predictions.index') }}" class="flex items-center gap-2 font-bold text-lg shrink-0">
                        <span>⚽</span>
                        <span class="hidden sm:inline">Soy Pachón Mundial</span>
                        <span class="sm:hidden">SoyPachón</span>
                    </a>

                    <!-- Desktop nav -->
                    <div class="hidden md:flex items-center gap-1 text-sm">
                        @php $route = request()->route()?->getName(); @endphp
                        <a href="{{ route('predictions.index') }}"
                           class="px-3 py-2 rounded-md transition {{ str_starts_with($route, 'predictions.') ? 'bg-pachon-green-dark' : 'hover:bg-white/10' }}">
                            Mis Pronósticos
                        </a>
                        <a href="{{ route('ranking.index') }}"
                           class="px-3 py-2 rounded-md transition {{ str_starts_with($route, 'ranking.') ? 'bg-pachon-green-dark' : 'hover:bg-white/10' }}">
                            Ranking
                        </a>
                        <a href="{{ route('profile.show') }}"
                           class="px-3 py-2 rounded-md transition {{ str_starts_with($route, 'profile.') ? 'bg-pachon-green-dark' : 'hover:bg-white/10' }}">
                            Mi Perfil
                        </a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}"
                               class="px-3 py-2 rounded-md transition {{ str_starts_with($route, 'admin.') ? 'bg-pachon-gold text-pachon-green-dark' : 'bg-pachon-gold/80 text-pachon-green-dark hover:bg-pachon-gold' }}">
                                ⚙️ Admin
                            </a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="ml-2">
                            @csrf
                            <button type="submit" class="bg-pachon-green-dark hover:bg-black/20 px-3 py-2 rounded-md transition">
                                Cerrar Sesión
                            </button>
                        </form>
                    </div>

                    <!-- Mobile toggle -->
                    <button type="button" class="md:hidden p-2 rounded-md hover:bg-white/10" @click="open = !open">
                        <svg x-show="!open" class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="open" x-cloak class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Mobile menu -->
                <div x-show="open" x-cloak class="md:hidden pb-3 space-y-1">
                    <a href="{{ route('predictions.index') }}" class="block px-3 py-2 rounded-md hover:bg-white/10">Mis Pronósticos</a>
                    <a href="{{ route('ranking.index') }}" class="block px-3 py-2 rounded-md hover:bg-white/10">Ranking</a>
                    <a href="{{ route('profile.show') }}" class="block px-3 py-2 rounded-md hover:bg-white/10">Mi Perfil</a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded-md bg-pachon-gold/80 text-pachon-green-dark hover:bg-pachon-gold">⚙️ Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-md hover:bg-white/10">Cerrar Sesión</button>
                    </form>
                </div>
            </div>
        </nav>
    @endauth

    <main class="flex-1">
        @if (session('status'))
            <div class="max-w-3xl mx-auto mt-4 px-4">
                <div class="bg-pachon-gold/20 border border-pachon-gold text-pachon-green-dark px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="text-center text-xs text-gray-400 py-6">
        ⚽ Soy Pachón Mundial — Polla del Mundial 2026
    </footer>
</body>
</html>
