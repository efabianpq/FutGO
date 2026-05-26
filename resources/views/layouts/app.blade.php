<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 min-h-screen flex flex-col">

    @auth
        <nav class="bg-pachon-green text-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-lg">
                    <span>⚽</span>
                    <span>Soy Pachón Mundial</span>
                </a>
                <div class="flex items-center gap-4 text-sm">
                    <span class="hidden sm:inline text-white/80">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="bg-pachon-green-dark hover:bg-black/20 px-3 py-1.5 rounded-md transition">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </nav>
    @endauth

    <main class="flex-1">
        @if (session('status'))
            <div class="max-w-2xl mx-auto mt-4 px-4">
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
