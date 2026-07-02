@extends('layouts.app')
@section('title', 'Modo rápido — buscar rival')

@php
    $levelLabels = [
        'recreativo'    => 'Recreativo',
        'intermedio'    => 'Intermedio',
        'competitivo'   => 'Competitivo',
        'elite_amateur' => 'Élite amateur',
    ];
@endphp

@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <a href="{{ route('social.oportunidades.index') }}" class="font-mono text-[12px] text-ink-mute hover:text-pitch">← Volver a oportunidades</a>
        <div class="flex items-center gap-2 mt-2">
            <span class="text-2xl">⚡</span>
            <h1 class="font-display font-bold text-display-s text-pitch uppercase">Modo rápido</h1>
        </div>
        <p class="text-ink-soft text-[14px] mt-1">¿Necesitas rival para hoy o mañana? Publícalo en segundos. Vence solo al llegar la fecha.</p>
    </div>

    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md text-[13px]">
            <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('social.oportunidades.store') }}"
          class="bg-white border-[1.5px] border-alerta/40 rounded-md shadow-card-2 p-5 sm:p-6 flex flex-col gap-4">
        @csrf
        <input type="hidden" name="type" value="BUSCAR_RIVAL">
        <input type="hidden" name="is_express" value="1">

        {{-- Equipo --}}
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Tu equipo</label>
            <select name="club_id" required class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                @foreach ($myClubs as $club)
                    <option value="{{ $club->id }}" @selected(old('club_id') == $club->id || $loop->first)>{{ $club->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Ciudad + nivel (pre-completados con tus datos) --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Ciudad *</label>
                <input type="text" name="city" value="{{ old('city', $prefillCity ?? '') }}" required maxlength="120" placeholder="Asunción"
                       class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Nivel *</label>
                <select name="required_level" class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                    @foreach ($levels as $l)
                        <option value="{{ $l }}" @selected(old('required_level', $prefillLevel) === $l)>{{ $levelLabels[$l] ?? $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Fecha más cercana sugerida --}}
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">¿Cuándo? *</label>
            <input type="datetime-local" name="window_start"
                   value="{{ old('window_start', $nearest->format('Y-m-d\TH:i')) }}" required
                   class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
            <p class="font-mono text-[10.5px] text-ink-mute">Sugerimos la disponibilidad más cercana. La oportunidad vence sola en esta fecha.</p>
        </div>

        {{-- Cancha --}}
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Cancha propuesta</label>
            <input type="text" name="cancha_propuesta" value="{{ old('cancha_propuesta') }}" maxlength="255"
                   class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('social.oportunidades.create') }}" class="btn btn-secondary btn-sm">Modo completo</a>
            <button type="submit" class="btn btn-primary btn-sm">⚡ Publicar urgente</button>
        </div>
    </form>
</div>
@endsection
