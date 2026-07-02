@extends('layouts.app')
@section('title', 'Centro de Soporte')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    @if($serviceIssues->isNotEmpty())
        <div class="mb-6 p-4 rounded-xl border border-yellow-400 bg-yellow-400/10">
            <p class="font-semibold text-yellow-600 dark:text-yellow-300">
                ⚠️ Hay problemas activos en la plataforma.
                <a href="{{ route('soporte.status') }}" class="underline">Ver estado del servicio →</a>
            </p>
        </div>
    @endif

    <h1 class="font-display text-2xl font-bold text-text mb-2">Centro de Soporte</h1>
    <p class="text-muted mb-8">¿En qué podemos ayudarte?</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        <a href="{{ route('soporte.chat') }}" class="block p-5 rounded-2xl border border-green/30 bg-green/5 hover:bg-green/10 transition">
            <div class="text-3xl mb-2">💬</div>
            <div class="font-semibold text-text">Asistente IA</div>
            <div class="text-sm text-muted mt-1">Respuestas inmediatas sobre cómo usar FutGO</div>
        </a>

        <a href="{{ route('soporte.knowledge') }}" class="block p-5 rounded-2xl border border-border hover:bg-surface-2 transition">
            <div class="text-3xl mb-2">📚</div>
            <div class="font-semibold text-text">Centro de ayuda</div>
            <div class="text-sm text-muted mt-1">Artículos y guías paso a paso</div>
        </a>

        <a href="{{ route('soporte.chat') }}?tipo=bug" class="block p-5 rounded-2xl border border-border hover:bg-surface-2 transition">
            <div class="text-3xl mb-2">🐞</div>
            <div class="font-semibold text-text">Reportar problema</div>
            <div class="text-sm text-muted mt-1">Algo no funciona como debería</div>
        </a>

        <a href="{{ route('soporte.chat') }}?tipo=sugerencia" class="block p-5 rounded-2xl border border-border hover:bg-surface-2 transition">
            <div class="text-3xl mb-2">💡</div>
            <div class="font-semibold text-text">Enviar sugerencia</div>
            <div class="text-sm text-muted mt-1">Compartí una idea para mejorar FutGO</div>
        </a>

        <a href="{{ route('soporte.features') }}" class="block p-5 rounded-2xl border border-border hover:bg-surface-2 transition">
            <div class="text-3xl mb-2">⭐</div>
            <div class="font-semibold text-text">Solicitar funcionalidad</div>
            <div class="text-sm text-muted mt-1">Votá las ideas de la comunidad</div>
        </a>

        <a href="{{ route('soporte.my-tickets') }}" class="block p-5 rounded-2xl border border-border hover:bg-surface-2 transition relative">
            @if($openTickets > 0)
                <span class="absolute top-3 right-3 bg-green text-white text-xs rounded-full px-2 py-0.5">{{ $openTickets }}</span>
            @endif
            <div class="text-3xl mb-2">📋</div>
            <div class="font-semibold text-text">Mis casos</div>
            <div class="text-sm text-muted mt-1">Seguimiento de tus consultas</div>
        </a>

        <a href="{{ route('soporte.status') }}" class="block p-5 rounded-2xl border border-border hover:bg-surface-2 transition sm:col-span-2 lg:col-span-3">
            <div class="text-3xl mb-2">❤️</div>
            <div class="font-semibold text-text">Estado del servicio</div>
            <div class="text-sm text-muted mt-1">Ver si hay problemas activos en la plataforma</div>
        </a>

    </div>
</div>
@endsection
