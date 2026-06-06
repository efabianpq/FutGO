@extends('layouts.app')
@section('title', 'Convocatoria · ' . $team->name)

@section('content')
@php
    $statusMeta = [
        'convocado'  => ['Convocado · sin responder', 'upcoming'],
        'confirmado' => ['Confirmó asistencia',       'win'],
        'declinado'  => ['Declinó',                   'default'],
    ];
@endphp
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('torneos.equipo.show', $tournament) }}" class="hover:text-pitch">Mi equipo</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Convocatoria</span>
    </nav>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    <div class="mb-6">
        <p class="eyebrow">{{ $tournament->name }} · {{ $match->phase->name }}</p>
        <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Convocatoria</h1>
        <p class="text-ink-soft text-[14px] mt-1">
            {{ $match->homeTeam?->name ?? 'Local' }} vs {{ $match->awayTeam?->name ?? 'Visitante' }}
            · {{ $match->scheduled_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
        </p>
        <p class="font-mono text-[12px] text-ink-mute mt-1">Equipo: <span class="text-pitch font-semibold">{{ $team->name }}</span></p>
    </div>

    <form method="POST" action="{{ route('torneos.convocatoria.store', [$tournament, $match]) }}">
        @csrf
        <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Plantilla — marcá los convocados</p>
            </div>
            @if ($players->isEmpty())
                <div class="p-8 text-center text-ink-soft">No hay jugadores activos en este equipo.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($players as $p)
                        @php
                            $cu = $callUps->get($p->id);
                            [$lbl, $variant] = $cu ? ($statusMeta[$cu->status] ?? [$cu->status, 'default']) : [null, null];
                        @endphp
                        <li class="flex items-center justify-between px-4 py-3">
                            <label class="flex items-center gap-3 cursor-pointer min-w-0">
                                <input type="checkbox" name="player_ids[]" value="{{ $p->id }}" @checked($cu)
                                       class="w-4 h-4 rounded border-line accent-pitch">
                                <span class="font-mono text-[12px] text-ink-mute w-7 text-right shrink-0">{{ $p->jersey_number ? '#'.$p->jersey_number : '—' }}</span>
                                <span class="min-w-0">
                                    <span class="font-semibold text-[14px] text-pitch block truncate">{{ $p->displayName() }}</span>
                                    <span class="font-mono text-[11px] text-ink-mute">{{ $p->position ?? 'Sin posición' }}</span>
                                </span>
                            </label>
                            @if ($cu)
                                <x-badge :variant="$variant">{{ $lbl }}</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="px-4 py-4 border-t border-line-soft flex justify-end">
                <x-btn type="submit" variant="primary">Guardar convocatoria</x-btn>
            </div>
        </div>
    </form>

    <p class="font-mono text-[11px] text-ink-mute mt-3">
        Los jugadores confirman o declinan su asistencia desde su sección «Mi Carrera».
    </p>
</div>
@endsection
