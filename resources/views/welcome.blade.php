@extends('layouts.app')

@section('title', \App\Support\Settings::tournamentName())

@section('content')
<div class="min-h-[calc(100vh-8rem)] bg-gradient-to-br from-pachon-green to-pachon-green-dark flex items-center justify-center text-white -mt-px">
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
                <a href="{{ route('ranking.index') }}" class="bg-white/10 hover:bg-white/20 text-white font-semibold py-3 px-6 rounded-lg transition">
                    Ver Ranking
                </a>
            </div>
        @else
            <a href="{{ route('predictions.index') }}" class="inline-block bg-pachon-gold hover:bg-pachon-gold-dark text-white font-semibold py-3 px-6 rounded-lg transition">
                Ir a Mis Pronósticos
            </a>
        @endguest
    </div>
</div>
@endsection
