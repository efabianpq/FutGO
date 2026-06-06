@extends('layouts.app')
@section('title', ($teamPlayer->displayName()) . ' · Estadísticas · ' . $tournament->name)

@section('content')

@php
$eventLabels = [
    'goal'             => ['⚽', 'Gol'],
    'own_goal'         => ['🔴', 'Gol en contra'],
    'assist'           => ['👟', 'Asistencia'],
    'yellow_card'      => ['🟨', 'Amarilla'],
    'red_card'         => ['🟥', 'Roja'],
    'substitution_in'  => ['↗', 'Entró'],
    'substitution_out' => ['↙', 'Salió'],
];
@endphp

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('torneos.estadisticas.index', $tournament) }}" class="hover:text-pitch">Estadísticas</a>
        <span>›</span>
        <span class="text-pitch font-semibold">{{ $teamPlayer->displayName() }}</span>
    </nav>

    {{-- Encabezado del jugador --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <x-avatar :user="$teamPlayer->user" :name="$teamPlayer->displayName()" size="lg" />
            <div>
            <p class="eyebrow">{{ $tournament->name }}</p>
            <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">
                {{ $teamPlayer->displayName() }}
            </h1>
            <div class="flex items-center gap-3 mt-2 flex-wrap">
                <div class="flex items-center gap-1.5">
                    @if ($teamPlayer->team?->color)
                        <span class="w-3 h-3 rounded-full border border-line"
                              style="background:{{ $teamPlayer->team->color }}"></span>
                    @endif
                    <span class="font-display font-semibold text-[14px] text-pitch">{{ $teamPlayer->team?->name }}</span>
                </div>
                @if ($teamPlayer->jersey_number)
                    <span class="font-mono text-[13px] text-ink-mute">#{{ $teamPlayer->jersey_number }}</span>
                @endif
                @if ($teamPlayer->position)
                    <span class="font-mono text-[12px] text-ink-mute uppercase">{{ $teamPlayer->position }}</span>
                @endif
                @if ($teamPlayer->isInactive())
                    <x-badge variant="default">Inactivo</x-badge>
                @endif
            </div>
            </div>
        </div>
    </div>

    {{-- Estadísticas resumen --}}
    @if ($stat)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">PJ</p>
                <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $stat->matches_played }}</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Goles</p>
                <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $stat->goals }}</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Asistencias</p>
                <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $stat->assists }}</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">⭐ Figuras</p>
                <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $stat->mvps }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">V / E / D</p>
                <p class="font-display font-bold text-xl text-pitch mt-1">
                    {{ $stat->wins }} / {{ $stat->draws }} / {{ $stat->losses }}
                </p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">🟨 Amarillas</p>
                <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $stat->yellow_cards }}</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">🟥 Rojas</p>
                <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $stat->red_cards }}</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Vallas invictas</p>
                <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $stat->clean_sheets }}</p>
            </div>
        </div>
    @else
        <div class="bg-bone-soft border border-line rounded-md p-6 mb-6 text-center">
            <p class="text-ink-mute">Este jugador aún no tiene estadísticas registradas.</p>
        </div>
    @endif

    {{-- Historial partido a partido --}}
    <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Historial de partidos</p>

    @if ($matchHistory->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-8 text-center">
            <p class="text-ink-mute">No hay partidos registrados con este jugador en la convocatoria.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($matchHistory as $entry)
                @php
                    $match  = $entry['match'];
                    $lineup = $entry['lineup'];
                    $events = $entry['events'];

                    $isHome     = $match->home_team_id === $teamPlayer->team_id;
                    $teamScore  = $isHome ? $match->home_score : $match->away_score;
                    $rivalScore = $isHome ? $match->away_score : $match->home_score;
                    $rival      = $isHome ? $match->awayTeam : $match->homeTeam;

                    if ($teamScore > $rivalScore)      $resultClass = 'bg-gol/10 border-gol/30';
                    elseif ($teamScore < $rivalScore)  $resultClass = 'bg-alerta/10 border-alerta/30';
                    else                               $resultClass = 'bg-bone-soft border-line';
                @endphp
                <div class="bg-white border border-line rounded-md shadow-card-2 p-4 {{ $resultClass }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-mono text-[11px] text-ink-mute uppercase">
                                {{ $match->phase?->name }} · #{{ $match->match_number }}
                            </p>
                            <p class="font-display font-bold text-pitch text-[14px] mt-0.5">
                                {{ $match->homeTeam?->name }} {{ $match->home_score }} – {{ $match->away_score }} {{ $match->awayTeam?->name }}
                            </p>
                        </div>
                        <div class="text-right text-[12px] font-mono text-ink-mute">
                            @if ($lineup)
                                {{ $lineup->started ? 'Titular' : 'Sustituto' }}
                            @endif
                        </div>
                    </div>

                    @if ($events->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach ($events as $ev)
                                @php [$icon, $label] = $eventLabels[$ev->type] ?? ['·', $ev->type]; @endphp
                                <span class="inline-flex items-center gap-1 font-mono text-[11px] bg-bone border border-line rounded px-2 py-0.5">
                                    {{ $icon }} {{ $label }} {{ $ev->minute }}'
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
