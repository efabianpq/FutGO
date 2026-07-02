@extends('layouts.app')
@section('title', 'Publicar oportunidad')

@php
    $levelLabels = [
        'recreativo'    => 'Recreativo',
        'intermedio'    => 'Intermedio',
        'competitivo'   => 'Competitivo',
        'elite_amateur' => 'Élite amateur',
    ];
    $initialType = in_array($preset, $types, true) ? $preset : old('type', 'BUSCAR_RIVAL');
    $targetPlayer = $targetPlayer ?? null;
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ type: @js($initialType) }">

    <div class="mb-6">
        <a href="{{ route('social.oportunidades.index') }}" class="font-mono text-[12px] text-ink-mute hover:text-pitch">← Volver a oportunidades</a>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-2">Publicar oportunidad</h1>
    </div>

    {{-- S2-A: oportunidad dirigida a un jugador (retar / invitar desde su ficha) --}}
    @if ($targetPlayer)
        <div class="mb-4 bg-gol/10 border border-gol/40 text-pitch-deep px-4 py-3 rounded-md text-[13px] flex items-center gap-2">
            <x-icon name="target" class="w-5 h-5" />
            <span>
                @if ($initialType === 'BUSCAR_RIVAL')
                    Estás <strong>retando a un amistoso</strong> a <strong>{{ $targetPlayer->name }}</strong> ({{ $targetPlayer->futgo_id }}).
                @else
                    Estás <strong>invitando a tu equipo</strong> a <strong>{{ $targetPlayer->name }}</strong> ({{ $targetPlayer->futgo_id }}).
                @endif
                Se publicará como oportunidad y le quedará registrada la invitación.
            </span>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md text-[13px]">
            <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($myClubs->isEmpty())
        <div class="mb-4 bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded-md text-[13px]">
            Para publicar como equipo (buscar rival/jugador/refuerzo) primero necesitas
            <a href="{{ route('torneos.mis-equipos') }}" class="underline font-semibold">crear un equipo y ser su capitán</a>.
            Igual puedes publicarte como <strong>jugador disponible</strong>.
        </div>
    @endif

    <form method="POST" action="{{ route('social.oportunidades.store') }}" class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 flex flex-col gap-4">
        @csrf

        @if ($targetPlayer)
            <input type="hidden" name="target_user_id" value="{{ old('target_user_id', $targetPlayer->id) }}">
        @endif

        {{-- Tipo --}}
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">¿Qué necesitas?</label>
            <select name="type" x-model="type" class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                @foreach ($types as $t)
                    <option value="{{ $t }}">{{ \App\Models\Social\Opportunity::TYPE_LABELS[$t] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Club (RIVAL/JUGADOR/REFUERZO) --}}
        <div class="flex flex-col gap-1" x-show="type !== 'BUSCAR_EQUIPO'" x-cloak>
            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Equipo (capitaneas)</label>
            <select name="club_id" class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                <option value="">Elige tu equipo…</option>
                @foreach ($myClubs as $club)
                    <option value="{{ $club->id }}" @selected(old('club_id') == $club->id)>{{ $club->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Ciudad / zona --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Ciudad *</label>
                <input type="text" name="city" value="{{ old('city', $prefillCity ?? '') }}" required maxlength="120" placeholder="Asunción"
                       class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
            </div>
            <div class="flex flex-col gap-1">
                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Zona / barrio</label>
                <input type="text" name="zone" value="{{ old('zone') }}" maxlength="120"
                       class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
            </div>
        </div>

        {{-- Nivel --}}
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Nivel *</label>
            <select name="required_level" class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                @foreach ($levels as $l)
                    <option value="{{ $l }}" @selected(old('required_level', $prefillLevel ?? $userLevel) === $l)>{{ $levelLabels[$l] ?? $l }}</option>
                @endforeach
            </select>
            <p class="font-mono text-[10.5px] text-ink-mute">El nivel filtra a quién le aparece tu oportunidad.</p>
        </div>

        {{-- ── BUSCAR_RIVAL ── --}}
        <template x-if="type === 'BUSCAR_RIVAL'">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Fecha y hora propuesta *</label>
                    <input type="datetime-local" name="window_start" value="{{ old('window_start') }}"
                           class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                </div>
                {{-- Búsqueda de cancha del catálogo (con fallback a texto libre) --}}
                @include('social.canchas._search_widget', [
                    'fieldName'   => 'venue_id',
                    'cityField'   => 'city',
                    'selectedId'  => old('venue_id'),
                    'selectedName'=> null,
                ])
                <div class="flex flex-col gap-1">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Cancha (texto libre, si no está en el catálogo)</label>
                    <input type="text" name="cancha_propuesta" value="{{ old('cancha_propuesta') }}" maxlength="255"
                           placeholder="Alternativa si no encuentras la cancha arriba"
                           class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                </div>
            </div>
        </template>

        {{-- ── BUSCAR_JUGADOR ── --}}
        <template x-if="type === 'BUSCAR_JUGADOR'">
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Posiciones buscadas</label>
                    <input type="text" name="posiciones" value="{{ old('posiciones') }}" maxlength="120" placeholder="Arquero, lateral…"
                           class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Cupos *</label>
                    <input type="number" name="cupos" value="{{ old('cupos', 1) }}" min="1" max="30"
                           class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                </div>
            </div>
        </template>

        {{-- ── BUSCAR_REFUERZO ── --}}
        <template x-if="type === 'BUSCAR_REFUERZO'">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Partido específico *</label>
                    <input type="text" name="partido" value="{{ old('partido') }}" maxlength="255" placeholder="vs Los Tigres, sáb 20/07 19:00"
                           class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Posición</label>
                        <input type="text" name="posicion" value="{{ old('posicion') }}" maxlength="60"
                               class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Fecha del partido</label>
                        <input type="datetime-local" name="window_start" value="{{ old('window_start') }}"
                               class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                    </div>
                </div>
            </div>
        </template>

        {{-- ── BUSCAR_EQUIPO ── --}}
        <template x-if="type === 'BUSCAR_EQUIPO'">
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Tu posición *</label>
                    <input type="text" name="posicion" value="{{ old('posicion') }}" maxlength="60" placeholder="Volante central"
                           class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Disponibilidad</label>
                    <input type="text" name="disponibilidad" value="{{ old('disponibilidad') }}" maxlength="255" placeholder="Fines de semana"
                           class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                </div>
            </div>
        </template>

        {{-- Descripción libre --}}
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Descripción</label>
            <textarea name="descripcion" rows="3" maxlength="1000" placeholder="Cuenta un poco más…"
                      class="px-3 py-2 bg-white border-[1.5px] border-line rounded-md text-[14px]">{{ old('descripcion') }}</textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('social.oportunidades.index') }}" class="btn btn-secondary btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-sm">Publicar oportunidad</button>
        </div>
    </form>
</div>
@endsection
