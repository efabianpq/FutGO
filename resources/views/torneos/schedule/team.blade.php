@extends('layouts.app')
@section('title', $team->name . ' · Cronograma · ' . $tournament->name)

@section('content')

@php
$statusMeta = [
    'scheduled' => ['Programado', 'upcoming'],
    'live'      => ['En vivo',   'live'],
    'finished'  => ['Finalizado','win'],
    'postponed' => ['Postpuesto','default'],
];
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('torneos.cronograma.index', $tournament) }}" class="hover:text-pitch">Cronograma</a>
        <span>›</span>
        <span class="text-pitch font-semibold">{{ $team->name }}</span>
    </nav>

    {{-- Cabecera del equipo --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div class="flex items-center gap-4">
            @if ($team->color)
                <div class="w-12 h-12 rounded-full border-2 border-line shrink-0 flex items-center justify-center"
                     style="background: {{ $team->color }}">
                </div>
            @endif
            <div>
                <p class="eyebrow">{{ $tournament->name }}</p>
                <h1 class="font-display font-bold text-display-m text-pitch uppercase">{{ $team->name }}</h1>
                <p class="font-mono text-[12px] text-ink-mute mt-0.5">
                    Capitán: {{ $team->captain?->name ?? '—' }}
                </p>
            </div>
        </div>
        <x-btn :href="route('torneos.cronograma.index', $tournament)" variant="ghost" size="sm">← Cronograma</x-btn>
    </div>

    {{-- Récord y estadísticas --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
        @php
            $pg = $standing?->won   ?? 0;
            $pe = $standing?->drawn ?? 0;
            $pp = $standing?->lost  ?? 0;
            $pj = $standing?->played ?? $finished->count();
        @endphp
        <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">PJ</p>
            <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $pj }}</p>
        </div>
        <div class="bg-white border border-gol/40 rounded-md shadow-card-2 p-4 text-center">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">PG</p>
            <p class="font-display font-extrabold text-3xl text-gol-deep mt-1">{{ $pg }}</p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">PE</p>
            <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $pe }}</p>
        </div>
        <div class="bg-white border border-alerta/30 rounded-md shadow-card-2 p-4 text-center">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">PP</p>
            <p class="font-display font-extrabold text-3xl text-alerta mt-1">{{ $pp }}</p>
        </div>
    </div>

    {{-- Goles --}}
    <div class="grid grid-cols-2 gap-3 mb-8 sm:grid-cols-4">
        <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center sm:col-start-2">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">GF</p>
            <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $goalsFor }}</p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">GC</p>
            <p class="font-display font-extrabold text-3xl text-pitch mt-1">{{ $goalsAgainst }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Próximos partidos --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Próximos partidos</p>
            <x-torneos.upcoming-matches
                :matches="$upcoming"
                :team-id="$team->id"
                :show-phase="true"
            />
        </div>

        {{-- Historial de resultados --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Historial</p>

            @if ($finished->isEmpty())
                <p class="text-[13px] text-ink-mute italic">Sin partidos finalizados todavía.</p>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($finished->sortByDesc('match_number') as $match)
                        @php
                            $isHome      = $match->home_team_id === $team->id;
                            $rival       = $isHome ? $match->awayTeam : $match->homeTeam;
                            $teamScore   = $isHome ? $match->home_score : $match->away_score;
                            $rivalScore  = $isHome ? $match->away_score : $match->home_score;
                            $outcome     = match (true) {
                                $match->winner_team_id === $team->id  => ['V', 'text-gol-deep', 'bg-gol/10 border-gol/30'],
                                $match->winner_team_id === null        => ['E', 'text-pitch',    'bg-bone-soft border-line'],
                                default                                => ['D', 'text-alerta',   'bg-alerta/5 border-alerta/20'],
                            };
                            [$outcomeLabel, $outcomeColor, $outcomeBg] = $outcome;
                        @endphp
                        <li class="py-3 flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center border {{ $outcomeBg }} shrink-0">
                                <span class="font-display font-extrabold text-[12px] {{ $outcomeColor }}">{{ $outcomeLabel }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-display font-semibold text-pitch text-[13px] truncate">
                                    vs {{ $rival?->name ?? '—' }}
                                </p>
                                <p class="font-mono text-[10px] text-ink-mute uppercase tracking-wide-label">
                                    {{ $match->phase?->name }}
                                    @if ($match->scheduled_at)
                                        · {{ $match->scheduled_at->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0 font-display font-extrabold text-[18px] text-pitch">
                                {{ $teamScore }} – {{ $rivalScore }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
