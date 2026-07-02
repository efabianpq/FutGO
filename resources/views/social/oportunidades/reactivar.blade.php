@extends('layouts.app')
@section('title', 'Reactivar disponibilidad')

@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <div class="mb-6">
        <a href="{{ route('social.oportunidades.index') }}" class="font-mono text-[12px] text-ink-mute hover:text-pitch">← Volver a oportunidades</a>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-2">Disponibilidad pausada</h1>
    </div>

    {{-- Alerta de pausa --}}
    <div class="rounded-lg border border-red-300 bg-red-50 p-5 mb-6">
        <div class="flex gap-3">
            <x-icon name="warning" class="w-5 h-5 text-red-500 shrink-0" />
            <div>
                <p class="font-semibold text-red-700 mb-1">Tu cuenta fue pausada automáticamente</p>
                <p class="text-sm text-red-600">
                    Acumulaste <strong>{{ \App\Services\Social\ReliabilityService::PAUSE_THRESHOLD }} o más no-shows</strong>
                    en los últimos <strong>{{ \App\Services\Social\ReliabilityService::PAUSE_WINDOW_DAYS }} días</strong>:
                    amistosos en los que no cargaste resultado ni te presentaste.
                </p>

                @if($score)
                <p class="text-sm text-red-600 mt-2">
                    Tu score de confiabilidad actual es <strong>{{ $score->score }}/100</strong>
                    ({{ $score->no_shows }} no-show{{ $score->no_shows !== 1 ? 's' : '' }} en los últimos 90 días).
                </p>
                @endif
            </div>
        </div>
    </div>

    @if($isPaused)
    <div class="bg-white rounded-lg border border-pitch/20 p-6">
        <h2 class="font-display font-semibold text-ink mb-3">¿Cómo reactivarme?</h2>
        <ul class="text-sm text-ink-mute space-y-2 list-disc list-inside mb-5">
            <li>Reconocé que incumpliste compromisos con otros equipos.</li>
            <li>Comprometete a cargar resultados en tus próximos amistosos.</li>
            <li>La reactivación es inmediata pero los no-shows siguen en tu historial.</li>
            <li>Si acumulás otros 2 no-shows en 30 días, la pausa se activa de nuevo.</li>
        </ul>

        <form method="POST" action="{{ route('social.oportunidades.reactivar.confirmar') }}">
            @csrf

            @if($errors->any())
            <div class="mb-4 text-sm text-red-600">{{ $errors->first() }}</div>
            @endif

            <label class="flex items-start gap-3 cursor-pointer mb-5">
                <input type="checkbox" name="acknowledged" value="1"
                       class="mt-0.5 h-4 w-4 rounded border-gray-400 text-pitch focus:ring-pitch"
                       {{ old('acknowledged') ? 'checked' : '' }}>
                <span class="text-sm text-ink leading-snug">
                    Entiendo que causé inconvenientes al no presentarme a compromisos acordados
                    y me comprometo a reportar resultados en el futuro.
                </span>
            </label>

            <button type="submit"
                    class="w-full bg-pitch text-white font-semibold py-2.5 px-6 rounded-lg hover:bg-pitch/90 transition">
                Confirmar y reactivar mi disponibilidad
            </button>
        </form>
    </div>
    @else
    {{-- Llegó a esta pantalla pero ya está activo (ej. reactivado por admin) --}}
    <div class="bg-white rounded-lg border border-green-200 p-6 text-center">
        <p class="text-green-700 font-semibold mb-3">Tu disponibilidad ya está activa</p>
        <a href="{{ route('social.oportunidades.create') }}"
           class="inline-block bg-pitch text-white font-semibold py-2 px-6 rounded-lg hover:bg-pitch/90 transition">
            Publicar oportunidad
        </a>
    </div>
    @endif

</div>
@endsection
