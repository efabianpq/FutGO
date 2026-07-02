@extends('layouts.app')
@section('title', 'Programar partido · ' . $tournament->name)

@section('content')
@include('admin.torneos._nav')

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <a href="{{ route('admin.torneos.partidos.index', $tournament) }}" class="hover:text-pitch">Partidos</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Programar #{{ $match->match_number }}</span>
    </nav>

    <div class="mb-6">
        <p class="eyebrow">{{ $match->phase?->name }}{{ $match->group ? ' · Grupo ' . $match->group->name : '' }}</p>
        <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Programación</h1>
        <p class="text-ink-soft text-[15px] mt-2 font-display font-semibold">
            {{ $match->homeTeam?->name ?? 'Por definir' }}
            <span class="font-mono text-ink-mute mx-2">vs</span>
            {{ $match->awayTeam?->name ?? 'Por definir' }}
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.torneos.partidos.programar.update', [$tournament, $match]) }}"
          class="bg-white border border-line rounded-md shadow-card-2 p-6 space-y-5">
        @csrf
        @method('PATCH')

        {{-- Fecha y hora --}}
        <div>
            <label for="scheduled_at" class="font-display font-bold text-pitch uppercase text-[13px] block mb-1">Fecha y hora</label>
            <input type="datetime-local" id="scheduled_at" name="scheduled_at"
                   value="{{ old('scheduled_at', $match->scheduled_at?->format('Y-m-d\TH:i')) }}"
                   class="w-full border border-line rounded-md px-3 py-2 text-[14px] font-mono focus:outline-none focus:border-pitch">
            <p class="text-[11px] text-ink-mute mt-1">Deja vacío si todavía no hay fecha definida.</p>
        </div>

        {{-- Sede --}}
        <div>
            <label for="venue" class="font-display font-bold text-pitch uppercase text-[13px] block mb-1">Sede / Cancha</label>
            <input type="text" id="venue" name="venue" maxlength="100"
                   value="{{ old('venue', $match->venue) }}"
                   placeholder="Ej.: Cancha 2 — Complejo Norte"
                   class="w-full border border-line rounded-md px-3 py-2 text-[14px] focus:outline-none focus:border-pitch">
        </div>

        {{-- Estado --}}
        <div>
            <label for="status" class="font-display font-bold text-pitch uppercase text-[13px] block mb-1">Estado</label>
            <select id="status" name="status"
                    class="w-full border border-line rounded-md px-3 py-2 text-[14px] font-mono focus:outline-none focus:border-pitch">
                @foreach (['scheduled' => 'Programado', 'live' => 'En vivo', 'postponed' => 'Postpuesto'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $match->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-ink-mute mt-1">Para finalizar el partido, carga el resultado desde la ficha del partido.</p>
        </div>

        {{-- Observaciones --}}
        <div>
            <label for="observations" class="font-display font-bold text-pitch uppercase text-[13px] block mb-1">Observaciones</label>
            <textarea id="observations" name="observations" rows="3" maxlength="500"
                      placeholder="Notas de programación: motivo de postergación, cambios de sede, etc."
                      class="w-full border border-line rounded-md px-3 py-2 text-[14px] focus:outline-none focus:border-pitch">{{ old('observations', $match->observations) }}</textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-btn type="submit" variant="primary">Guardar programación</x-btn>
            <x-btn :href="route('admin.torneos.partidos.index', $tournament)" variant="link">Cancelar</x-btn>
        </div>
    </form>
</div>
@endsection
