@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-10">
    <div class="bg-white rounded-lg shadow-md p-8">
        <h1 class="text-3xl font-bold text-pachon-green mb-2">
            ¡Hola, {{ explode(' ', auth()->user()->name)[0] }}! ⚽
        </h1>
        <p class="text-gray-600">
            Bienvenido al Dashboard de Soy Pachón Mundial. Tu cuenta está activa.
        </p>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="border border-pachon-green/20 rounded-lg p-4">
                <p class="text-xs uppercase text-gray-500">Estado</p>
                <p class="font-semibold text-pachon-green mt-1">Cuenta activada ✓</p>
            </div>
            <div class="border border-pachon-green/20 rounded-lg p-4">
                <p class="text-xs uppercase text-gray-500">Código usado</p>
                <p class="font-mono font-semibold mt-1">{{ auth()->user()->invitation_code ?? '—' }}</p>
            </div>
            <div class="border border-pachon-green/20 rounded-lg p-4">
                <p class="text-xs uppercase text-gray-500">Rol</p>
                <p class="font-semibold mt-1 capitalize">{{ auth()->user()->role }}</p>
            </div>
        </div>

        <div class="mt-8 bg-pachon-gold/10 border border-pachon-gold/30 rounded-lg p-4 text-sm text-gray-700">
            <strong>Próximos pasos del MVP:</strong> Mi Polla (ingreso de pronósticos), Ranking público,
            Panel admin para resultados oficiales. En construcción.
        </div>
    </div>
</div>
@endsection
