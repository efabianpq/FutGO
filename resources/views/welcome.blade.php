<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'SoyPachonMundial') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gradient-to-br from-pachon-green to-pachon-green-dark min-h-screen text-white">
        <div class="container mx-auto px-6 py-16 text-center">
            <h1 class="text-5xl md:text-6xl font-bold mb-4">
                ⚽ Soy Pachón Mundial
            </h1>
            <p class="text-xl text-pachon-gold font-semibold mb-2">
                Polla del Mundial FIFA 2026
            </p>
            <p class="text-white/80 max-w-2xl mx-auto">
                Plataforma de pronósticos para el Mundial 2026. Estructura base lista — Fase 1 MVP en desarrollo.
            </p>

            <div class="mt-12 inline-block bg-white/10 backdrop-blur rounded-xl px-6 py-4 ring-1 ring-white/20">
                <p class="text-sm text-white/70">Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</p>
            </div>
        </div>
    </body>
</html>
