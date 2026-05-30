@extends('layouts.app')
@section('title', 'Cronograma · ' . $tournament->name)

@section('content')

@php
$statusMeta = [
    'scheduled' => ['Programado', 'upcoming', 'text-ink-mute'],
    'live'      => ['En vivo',   'live',     'text-gol-deep'],
    'finished'  => ['Finalizado','win',      'text-pitch'],
    'postponed' => ['Postpuesto','default',  'text-ink-mute'],
];
$tournamentStatusMeta = [
    'draft'       => ['Borrador',    'upcoming'],
    'open'        => ['Inscripción', 'win'],
    'in_progress' => ['En juego',    'live'],
    'finished'    => ['Finalizado',  'default'],
];
[$tLabel, $tVariant] = $tournamentStatusMeta[$tournament->status] ?? [$tournament->status, 'default'];

// Colección plana de todos los partidos para los filtros JS-less de Alpine
$allMatches = $phases->flatMap(fn ($p) => $p->matches->map(fn ($m) => $m->setRelation('phase', $p)));
$hasScheduledAt = $allMatches->whereNotNull('scheduled_at')->isNotEmpty();
@endphp

{{-- Alpine state: se inicializa con datos del servidor para filtrar en cliente --}}
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{
        filterStatus: 'all',
        filterPhase:  'all',
        filterTeam:   'all',
        matchVisible(matchStatus, phaseId, homeId, awayId) {
            if (this.filterStatus !== 'all') {
                if (this.filterStatus === 'upcoming' && matchStatus !== 'scheduled' && matchStatus !== 'live') return false;
                if (this.filterStatus === 'finished' && matchStatus !== 'finished') return false;
            }
            if (this.filterPhase !== 'all' && phaseId != this.filterPhase) return false;
            if (this.filterTeam  !== 'all' && this.filterTeam != homeId && this.filterTeam != awayId) return false;
            return true;
        }
     }">

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">Cronograma</p>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">{{ $tournament->name }}</h1>
            <div class="flex items-center gap-3 mt-1">
                <x-badge :variant="$tVariant">{{ $tLabel }}</x-badge>
                <span class="font-mono text-[12px] text-ink-mute">{{ ucfirst($tournament->sport) }}</span>
            </div>
        </div>
        <a href="{{ url()->previous() }}"
           class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline self-start">← Volver</a>
    </div>

    @if ($phases->isEmpty() || $allMatches->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">El fixture aún no fue generado para este torneo.</p>
        </div>
    @else

        {{-- ── Filtros ─────────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap gap-3 mb-6">

            {{-- Estado --}}
            <div class="flex gap-1.5 flex-wrap">
                @foreach (['all' => 'Todos', 'upcoming' => 'Próximos', 'finished' => 'Finalizados'] as $val => $label)
                    <button @click="filterStatus = '{{ $val }}'"
                            :class="filterStatus === '{{ $val }}'
                                ? 'bg-pitch text-bone'
                                : 'bg-white text-pitch border border-pitch hover:bg-pitch hover:text-bone'"
                            class="px-3 py-1.5 rounded-md font-display font-bold uppercase text-[12px] tracking-wide-cta transition-all duration-fast">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($phases->count() > 1)
                <div>
                    <select x-model="filterPhase"
                            class="border border-line rounded-md px-3 py-1.5 text-[13px] font-mono focus:outline-none focus:border-pitch bg-white">
                        <option value="all">Todas las fases</option>
                        @foreach ($phases as $phase)
                            <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($teams->count() > 1)
                <div>
                    <select x-model="filterTeam"
                            class="border border-line rounded-md px-3 py-1.5 text-[13px] font-mono focus:outline-none focus:border-pitch bg-white">
                        <option value="all">Todos los equipos</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        {{-- ── Partidos por fase ────────────────────────────────────────────── --}}
        @foreach ($phases as $phase)
            @php
                $phaseMatches = $phase->matches;
                // Agrupar por fecha (o "Sin fecha" si scheduled_at es null)
                $byDate = $phaseMatches->groupBy(fn ($m) =>
                    $m->scheduled_at ? $m->scheduled_at->format('Y-m-d') : '__nodate__'
                )->sortKeys();
            @endphp

            <div x-show="filterPhase === 'all' || filterPhase === '{{ $phase->id }}'">
                <div class="flex items-center gap-2 mt-8 mb-4">
                    <p class="font-display font-bold text-pitch uppercase text-[15px]">{{ $phase->name }}</p>
                    @if ($phase->is_active)
                        <x-badge variant="live">Activa</x-badge>
                    @elseif ($phase->isCompleted())
                        <x-badge variant="default">Cerrada</x-badge>
                    @endif
                </div>

                @foreach ($byDate as $dateKey => $dayMatches)
                    @php
                        $dateLabel = $dateKey === '__nodate__'
                            ? 'Sin fecha definida'
                            : \Carbon\Carbon::parse($dateKey)->isoFormat('dddd D [de] MMMM');
                    @endphp

                    {{-- Agrupador de fecha --}}
                    <div class="mb-4">
                        <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mb-2 flex items-center gap-2">
                            <span class="w-full h-px bg-line-soft mr-2 hidden sm:block"></span>
                            {{ $dateLabel }}
                        </p>

                        <div class="space-y-2">
                            @foreach ($dayMatches as $match)
                                @php
                                    [$statusLabel, $statusVariant, $scoreClass] = $statusMeta[$match->status] ?? [$match->status, 'default', 'text-pitch'];
                                    $isLive = $match->isLive();
                                    $isFinished = $match->isFinished();
                                @endphp

                                <div x-show="matchVisible('{{ $match->status }}', {{ $phase->id }}, {{ $match->home_team_id ?? 0 }}, {{ $match->away_team_id ?? 0 }})"
                                     x-transition
                                     class="bg-white border {{ $isLive ? 'border-gol shadow-[0_0_0_2px_theme(colors.gol/30%)]' : 'border-line' }} rounded-md shadow-card-2 p-4">

                                    <div class="flex flex-wrap items-center gap-3">

                                        {{-- Hora / Fase --}}
                                        <div class="w-16 shrink-0 text-center hidden sm:block">
                                            @if ($match->scheduled_at)
                                                <p class="font-mono text-[12px] font-semibold text-pitch">{{ $match->scheduled_at->format('H:i') }}</p>
                                            @else
                                                <p class="font-mono text-[11px] text-ink-mute">—</p>
                                            @endif
                                            @if ($match->group)
                                                <p class="font-mono text-[10px] text-ink-mute uppercase tracking-wide-label mt-0.5">
                                                    Grp {{ $match->group->name }}
                                                </p>
                                            @endif
                                        </div>

                                        {{-- Equipo local --}}
                                        <div class="flex-1 text-right min-w-0">
                                            <div class="flex items-center justify-end gap-2">
                                                @if ($match->homeTeam?->color)
                                                    <span class="w-3 h-3 rounded-full border border-line/50 shrink-0"
                                                          style="background:{{ $match->homeTeam->color }}"></span>
                                                @endif
                                                @if ($match->home_team_id && $match->homeTeam)
                                                    <a href="{{ route('torneos.cronograma.team', [$tournament, $match->home_team_id]) }}"
                                                       class="font-display font-bold text-pitch text-[15px] hover:underline truncate">
                                                        {{ $match->homeTeam->name }}
                                                    </a>
                                                @else
                                                    <span class="font-display font-bold text-ink-mute text-[15px] truncate">Por definir</span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Marcador / vs --}}
                                        <div class="shrink-0 text-center w-20">
                                            @if ($isLive)
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <span class="font-display font-extrabold text-[20px] text-gol-deep">
                                                        {{ $match->home_score ?? '0' }} – {{ $match->away_score ?? '0' }}
                                                    </span>
                                                </div>
                                                <p class="font-mono text-[10px] uppercase text-gol-deep tracking-wide-label animate-pulse">En vivo</p>
                                            @elseif ($isFinished)
                                                <span class="font-display font-extrabold text-[20px] {{ $scoreClass }}">
                                                    {{ $match->home_score }} – {{ $match->away_score }}
                                                </span>
                                            @else
                                                <span class="font-mono text-[14px] text-ink-mute">vs</span>
                                                @if ($match->scheduled_at)
                                                    <p class="font-mono text-[10px] text-ink-mute block sm:hidden">
                                                        {{ $match->scheduled_at->format('H:i') }}
                                                    </p>
                                                @endif
                                            @endif
                                        </div>

                                        {{-- Equipo visitante --}}
                                        <div class="flex-1 text-left min-w-0">
                                            <div class="flex items-center gap-2">
                                                @if ($match->away_team_id && $match->awayTeam)
                                                    <a href="{{ route('torneos.cronograma.team', [$tournament, $match->away_team_id]) }}"
                                                       class="font-display font-bold text-pitch text-[15px] hover:underline truncate">
                                                        {{ $match->awayTeam->name }}
                                                    </a>
                                                @else
                                                    <span class="font-display font-bold text-ink-mute text-[15px] truncate">Por definir</span>
                                                @endif
                                                @if ($match->awayTeam?->color)
                                                    <span class="w-3 h-3 rounded-full border border-line/50 shrink-0"
                                                          style="background:{{ $match->awayTeam->color }}"></span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Badge estado --}}
                                        <div class="shrink-0 hidden sm:block">
                                            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                                        </div>
                                    </div>

                                    {{-- Ganador highlight --}}
                                    @if ($isFinished && $match->winner_team_id)
                                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol-deep text-center mt-2">
                                            Ganó {{ $match->winner_team_id === $match->home_team_id ? $match->homeTeam?->name : $match->awayTeam?->name }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- Mensaje cuando ningún partido pasa el filtro --}}
        <p x-show="document.querySelectorAll('[x-show].bg-white:not([style*=\'display: none\'])').length === 0"
           class="text-center text-ink-mute text-[13px] mt-8">
            Ningún partido coincide con los filtros seleccionados.
        </p>
    @endif
</div>
@endsection
