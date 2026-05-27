@extends('layouts.app')

@section('title', 'Soy Pachón Mundial')

@section('content')
<div class="bg-gradient-to-br from-pachon-green to-pachon-green-dark text-white -mt-px">
    <div class="container mx-auto px-6 py-16 text-center">
        <h1 class="text-5xl md:text-6xl font-bold mb-4">⚽ Soy Pachón Mundial</h1>
        <p class="text-xl text-pachon-gold font-semibold mb-2">{{ \App\Support\Settings::tournamentName() }}</p>
        <p class="text-white/80 max-w-2xl mx-auto mb-8">
            {{ \App\Support\Settings::welcomeMessage() }}
        </p>

        @guest
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('login') }}" class="bg-white text-pachon-green hover:bg-pachon-gold hover:text-white font-semibold py-3 px-6 rounded-lg transition">
                    Iniciar sesión
                </a>
                <a href="{{ route('register') }}" class="bg-pachon-gold hover:bg-pachon-gold-dark text-white font-semibold py-3 px-6 rounded-lg transition">
                    Crear cuenta
                </a>
            </div>
        @else
            <a href="{{ route('predictions.index') }}" class="inline-block bg-pachon-gold hover:bg-pachon-gold-dark text-white font-semibold py-3 px-6 rounded-lg transition">
                Ir a Mis Pronósticos
            </a>
        @endguest
    </div>

    <!-- Sección del video explicativo -->
    <div class="container mx-auto px-6 pb-16">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-center text-2xl font-bold text-pachon-gold mb-4">🎥 ¿Cómo funciona?</h2>
            @php $embed = \App\Support\Settings::videoEmbedUrl(); @endphp
            @if ($embed)
                <div class="aspect-video w-full rounded-lg overflow-hidden shadow-2xl bg-black">
                    <iframe src="{{ $embed }}"
                            title="Video explicativo"
                            class="w-full h-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                </div>
            @else
                <div class="aspect-video w-full rounded-lg bg-white/10 border-2 border-dashed border-white/30 flex flex-col items-center justify-center text-center p-6">
                    <div class="text-6xl mb-3">🎬</div>
                    <p class="font-semibold text-white">Video explicativo próximamente</p>
                    <p class="text-sm text-white/70 mt-1">El administrador puede configurar la URL del video desde el panel.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
