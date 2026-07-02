@extends('layouts.app')
@section('title', $tournament->name)

@section('content')

@php
$statusMeta = [
    'draft'       => ['Borrador',    'upcoming'],
    'open'        => ['Inscripción', 'win'],
    'in_progress' => ['En juego',    'live'],
    'finished'    => ['Finalizado',  'default'],
    'cancelled'   => ['Cancelado',   'default'],
];
[$statusLabel, $statusVariant] = $statusMeta[$tournament->status] ?? [$tournament->status, 'default'];

$categoryLabels = [
    'libre' => 'Libre', 'veteranos' => 'Veteranos', 'sub15' => 'Sub-15',
    'sub17' => 'Sub-17', 'sub20' => 'Sub-20', 'femenino' => 'Femenino', 'mixto' => 'Mixto',
];

$matchStatusMeta = [
    'scheduled' => ['Programado', 'upcoming'],
    'live'      => ['En vivo',   'live'],
    'finished'  => ['Finalizado','win'],
    'postponed' => ['Postpuesto','default'],
];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- ══ 1. CABECERA ══════════════════════════════════════════════════════ --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-8">
        @if ($tournament->banner_url)
            <div class="h-32 sm:h-40 bg-pitch-mist bg-cover bg-center"
                 style="background-image:url('{{ $tournament->banner_url }}')"></div>
        @endif
        <div class="p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    @if ($tournament->logo_url)
                        <img src="{{ $tournament->logo_url }}" alt="{{ $tournament->name }}"
                             class="w-16 h-16 rounded-md object-cover border border-line shrink-0">
                    @endif
                    <div class="min-w-0">
                        <p class="eyebrow">Centro de información</p>
                        <h1 class="font-display font-bold text-2xl sm:text-display-s md:text-display-m text-pitch uppercase mt-1 break-words">{{ $tournament->name }}</h1>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                            <span class="font-mono text-[12px] text-ink-mute uppercase tracking-wide-label">
                                {{ $categoryLabels[$tournament->category] ?? $tournament->category }}
                            </span>
                            <span class="font-mono text-[12px] text-ink-mute">· {{ ucfirst($tournament->sport) }}</span>
                            @if ($tournament->city)
                                <span class="font-mono text-[12px] text-ink-mute">· {{ $tournament->city }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Accesos --}}
                <div class="flex flex-wrap gap-2">
                    <x-btn :href="route('torneos.cronograma.index', $tournament)" variant="ghost" size="sm">Cronograma</x-btn>
                    <x-btn :href="route('torneos.estadisticas.index', $tournament)" variant="ghost" size="sm">Estadísticas</x-btn>
                </div>
            </div>

            {{-- Meta-datos --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
                <div>
                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Equipos</p>
                    <p class="font-display font-bold text-pitch text-[15px]">{{ $teams->count() }}</p>
                </div>
                @if ($activePhase)
                    <div>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Fase actual</p>
                        <p class="font-display font-bold text-pitch text-[15px]">{{ $activePhase->name }}</p>
                    </div>
                @endif
                @if ($tournament->starts_at)
                    <div>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Inicio</p>
                        <p class="font-display font-bold text-pitch text-[15px]">{{ $tournament->starts_at->format('d/m/Y') }}</p>
                    </div>
                @endif
                @if ($tournament->ends_at)
                    <div>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Fin</p>
                        <p class="font-display font-bold text-pitch text-[15px]">{{ $tournament->ends_at->format('d/m/Y') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Columna principal --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- ══ 2. PRÓXIMOS PARTIDOS ════════════════════════════════════════ --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-display font-bold text-pitch uppercase text-[15px]">Próximos partidos</p>
                    <a href="{{ route('torneos.cronograma.index', $tournament) }}"
                       class="text-[12px] font-display font-semibold uppercase text-pitch hover:underline">Ver cronograma →</a>
                </div>
                <x-torneos.upcoming-matches :matches="$upcomingMatches" :limit="5" :show-phase="true" />
            </section>

            {{-- ══ 3. ÚLTIMOS RESULTADOS ═══════════════════════════════════════ --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Últimos resultados</p>

                @if ($latestResults->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Todavía no hay resultados registrados.</p>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($latestResults as $match)
                            @php
                                $homeWon = $match->winner_team_id && $match->winner_team_id === $match->home_team_id;
                                $awayWon = $match->winner_team_id && $match->winner_team_id === $match->away_team_id;
                            @endphp
                            <li class="py-3 flex items-center gap-3">
                                <div class="flex-1 text-right min-w-0">
                                    <span class="font-display font-semibold text-[14px] truncate {{ $homeWon ? 'text-pitch font-bold' : 'text-ink-soft' }}">
                                        {{ $match->homeTeam?->name ?? 'Por definir' }}
                                    </span>
                                </div>
                                <div class="shrink-0 text-center">
                                    <span class="font-display font-extrabold text-[18px] text-pitch px-2">
                                        {{ $match->home_score }} – {{ $match->away_score }}
                                    </span>
                                    @if ($match->relationLoaded('phase') && $match->phase)
                                        <p class="font-mono text-[9px] uppercase tracking-wide-label text-ink-mute">{{ $match->phase->name }}</p>
                                    @endif
                                </div>
                                <div class="flex-1 text-left min-w-0">
                                    <span class="font-display font-semibold text-[14px] truncate {{ $awayWon ? 'text-pitch font-bold' : 'text-ink-soft' }}">
                                        {{ $match->awayTeam?->name ?? 'Por definir' }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ══ 4. TABLA DE POSICIONES RESUMIDA ═════════════════════════════ --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-display font-bold text-pitch uppercase text-[15px]">Tabla de posiciones</p>
                    @if ($canManage && $standingsPhase)
                        <a href="{{ route('admin.torneos.standings.index', $tournament) }}"
                           class="text-[12px] font-display font-semibold uppercase text-pitch hover:underline">Ver tabla completa →</a>
                    @endif
                </div>

                @if (! $standingsPhase)
                    <p class="text-[13px] text-ink-mute italic">Este torneo no tiene fase de grupos.</p>
                @else
                    @php
                        $anyStandings = $standingsPhase->groups->contains(fn ($g) => $g->standings->isNotEmpty());
                        $classifies = (int) ($tournament->classifies_per_group ?? 0);
                    @endphp

                    @if (! $anyStandings)
                        <div class="bg-bone-soft border border-line rounded-md p-4 text-center">
                            <p class="text-[13px] text-ink-mute">Pendiente de cálculo.</p>
                            <p class="text-[11px] text-ink-mute mt-1">La tabla aparecerá cuando se registren resultados.</p>
                        </div>
                    @else
                        <div class="space-y-5">
                            @foreach ($standingsPhase->groups as $group)
                                @if ($group->standings->isNotEmpty())
                                    <div>
                                        @if ($standingsPhase->groups->count() > 1)
                                            <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mb-2">Grupo {{ $group->name }}</p>
                                        @endif
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left">
                                                <thead>
                                                    <tr class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute border-b border-line">
                                                        <th class="py-2 w-6">#</th>
                                                        <th class="py-2">Equipo</th>
                                                        <th class="py-2 text-center w-10">PJ</th>
                                                        <th class="py-2 text-center w-10">DG</th>
                                                        <th class="py-2 text-center w-12 text-pitch">PTS</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-line-soft">
                                                    @foreach ($group->standings->take(4) as $standing)
                                                        @php $qualifies = $classifies > 0 && $standing->position <= $classifies; @endphp
                                                        <tr class="{{ $qualifies ? 'bg-gol/5' : '' }}">
                                                            <td class="py-2 font-mono text-[12px] {{ $qualifies ? 'text-gol-deep font-bold' : 'text-ink-mute' }}">{{ $standing->position }}</td>
                                                            <td class="py-2">
                                                                <div class="flex items-center gap-1.5">
                                                                    @if ($standing->team?->color)
                                                                        <span class="w-2.5 h-2.5 rounded-full border border-line/50 shrink-0" style="background:{{ $standing->team->color }}"></span>
                                                                    @endif
                                                                    <span class="font-display font-semibold text-pitch text-[13px] truncate">{{ $standing->team?->name ?? 'Por definir' }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="py-2 text-center font-mono text-[12px]">{{ $standing->played }}</td>
                                                            <td class="py-2 text-center font-mono text-[12px] {{ $standing->goal_difference > 0 ? 'text-gol-deep' : ($standing->goal_difference < 0 ? 'text-alerta' : '') }}">
                                                                {{ $standing->goal_difference > 0 ? '+' : '' }}{{ $standing->goal_difference }}
                                                            </td>
                                                            <td class="py-2 text-center font-display font-extrabold text-[14px] text-pitch">{{ $standing->points }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endif
            </section>
        </div>

        {{-- Columna lateral --}}
        <div class="space-y-6">

            {{-- ══ BASES DEL TORNEO ════════════════════════════════════════════ --}}
            @php
                $formatLabels = [
                    'groups_and_knockout' => 'Grupos + Eliminación',
                    'knockout_only'       => 'Solo eliminación',
                    'round_robin'         => 'Todos contra todos',
                ];
            @endphp
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Bases del torneo</p>
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Formato</dt><dd class="font-semibold text-pitch text-right">{{ $formatLabels[$tournament->format] ?? $tournament->format }}</dd></div>
                    @if ($tournament->venue)
                        <div class="flex justify-between gap-3"><dt class="text-ink-mute">Sede</dt><dd class="font-semibold text-pitch text-right">{{ $tournament->venue }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-mute">Inscripción</dt>
                        <dd class="font-semibold text-pitch text-right">{{ (int) $tournament->registration_fee > 0 ? '$' . number_format($tournament->registration_fee, 0, ',', '.') : 'Gratuita' }}</dd>
                    </div>
                    @if ($tournament->prize_description)
                        <div class="flex justify-between gap-3"><dt class="text-ink-mute">Premio</dt><dd class="font-semibold text-pitch text-right">{{ $tournament->prize_description }}</dd></div>
                    @endif
                </dl>
                @if ($tournament->rules)
                    <details class="mt-3 group">
                        <summary class="cursor-pointer font-mono text-[11px] uppercase tracking-wide-label text-pitch hover:underline">Ver reglamento</summary>
                        <p class="text-[12px] text-ink-soft mt-2 whitespace-pre-line">{{ $tournament->rules }}</p>
                    </details>
                @endif
            </section>

            {{-- ══ 5. TOP GOLEADORES ═══════════════════════════════════════════ --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-display font-bold text-pitch uppercase text-[15px]">Goleadores</p>
                    <a href="{{ route('torneos.estadisticas.index', $tournament) }}"
                       class="text-[12px] font-display font-semibold uppercase text-pitch hover:underline">Ver todo →</a>
                </div>

                @if ($topScorers->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Sin estadísticas registradas aún.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute border-b border-line">
                                    <th class="py-2 w-5">#</th>
                                    <th class="py-2">Jugador</th>
                                    <th class="py-2 text-center w-8" title="Goles">⚽</th>
                                    <th class="py-2 text-center w-8" title="Asistencias">👟</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft">
                                @foreach ($topScorers as $i => $stat)
                                    <tr>
                                        <td class="py-2 font-mono text-[11px] text-ink-mute">{{ $i + 1 }}</td>
                                        <td class="py-2 min-w-0">
                                            <p class="font-display font-semibold text-pitch text-[13px] truncate">{{ $stat->teamPlayer?->user?->name ?? '—' }}</p>
                                            <p class="font-mono text-[10px] text-ink-mute truncate">{{ $stat->teamPlayer?->team?->name ?? '—' }}</p>
                                        </td>
                                        <td class="py-2 text-center font-mono font-bold text-[14px] text-pitch">{{ $stat->goals }}</td>
                                        <td class="py-2 text-center font-mono text-[13px]">{{ $stat->assists }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- ══ 6. EQUIPOS PARTICIPANTES ════════════════════════════════════ --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">
                    Equipos participantes
                    <span class="font-mono text-[12px] text-ink-mute">({{ $teams->count() }})</span>
                </p>

                @if ($teams->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Aún no hay equipos aprobados.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($teams as $team)
                            <li>
                                <a href="{{ route('torneos.cronograma.team', [$tournament, $team]) }}"
                                   class="flex items-center gap-3 p-2 rounded-md hover:bg-bone-soft transition-colors duration-fast group">
                                    {{-- Escudo / inicial --}}
                                    @if ($team->shield_url)
                                        <img src="{{ $team->shield_url }}" alt="{{ $team->name }}"
                                             class="w-9 h-9 rounded-full object-cover border border-line shrink-0">
                                    @else
                                        <span class="w-9 h-9 rounded-full border border-line shrink-0 flex items-center justify-center font-display font-bold text-pitch text-[14px]"
                                              style="background:{{ $team->color ?? '#E8E8E8' }}">
                                            {{ mb_strtoupper(mb_substr($team->name, 0, 1)) }}
                                        </span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="font-display font-semibold text-pitch text-[13px] truncate group-hover:underline">{{ $team->name }}</p>
                                        <p class="font-mono text-[10px] text-ink-mute truncate">
                                            Cap.: {{ $team->captain?->name ?? '—' }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 font-mono text-[11px] text-ink-mute">
                                        {{ $team->players_count }} 👤
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
