@extends('layouts.app')

@section('title', 'Soy Pachón Mundial — Polla del Mundial 2026')

@section('content')
<div class="min-h-[calc(100vh-8rem)] bg-gradient-to-br from-pachon-green to-pachon-green-dark flex items-center justify-center text-white -mt-px">
    <div class="container mx-auto px-6 py-16 text-center">
        <h1 class="text-5xl md:text-6xl font-bold mb-4">⚽ Soy Pachón Mundial</h1>
        <p class="text-xl text-pachon-gold font-semibold mb-2">Polla del Mundial FIFA 2026</p>
        <p class="text-white/80 max-w-2xl mx-auto mb-8">
            Pronostica los 104 partidos del Mundial, compite con tus amigos y gana premios reales.
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
            <a href="{{ route('dashboard') }}" class="inline-block bg-pachon-gold hover:bg-pachon-gold-dark text-white font-semibold py-3 px-6 rounded-lg transition">
                Ir al dashboard
            </a>
        @endguest
    </div>
</div>
@endsection
