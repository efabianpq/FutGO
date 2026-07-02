@extends('layouts.app')
@section('title', 'Estadísticas · ' . $tournament->name)

@section('content')

@php
$statusMeta = [
    'draft'       => ['Borrador',    'upcoming'],
    'open'        => ['Inscripción', 'win'],
    'in_progress' => ['En juego',    'live'],
    'finished'    => ['Finalizado',  'default'],
];
[$statusLabel, $statusVariant] = $statusMeta[$tournament->status] ?? [$tournament->status, 'default'];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ filterTeam: 'all', showAll: false, q: '', match(s){ return this.q === '' || s.toLowerCase().includes(this.q.toLowerCase()); } }">

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="min-w-0">
            <p class="eyebrow">Estadísticas</p>
            <h1 class="font-display font-bold text-2xl sm:text-display-s md:text-display-m text-pitch uppercase mt-1 break-words">{{ $tournament->name }}</h1>
            <div class="flex items-center gap-3 mt-1">
                <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                <span class="font-mono text-[12px] text-ink-mute">{{ ucfirst($tournament->sport) }}</span>
            </div>
        </div>
        <a href="{{ url()->previous() }}"
           class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline">← Volver</a>
    </div>

    @if ($stats->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">Todavía no hay estadísticas registradas para este torneo.</p>
            <p class="text-[13px] text-ink-mute mt-2">Las estadísticas aparecerán aquí cuando se ingresen resultados con convocatorias.</p>
        </div>
    @else

        {{-- Filtro por equipo --}}
        @if ($teams->count() > 1)
            <div class="flex flex-wrap gap-2 mb-5">
                <button @click="filterTeam = 'all'"
                        :class="filterTeam === 'all'
                            ? 'bg-pitch text-bone'
                            : 'bg-white text-pitch border border-pitch hover:bg-pitch hover:text-bone'"
                        class="px-3 py-1.5 rounded-md font-display font-bold uppercase text-[12px] tracking-wide-cta transition-all duration-fast">
                    Todos
                </button>
                @foreach ($teams as $team)
                    <button @click="filterTeam = '{{ $team->id }}'"
                            :class="filterTeam === '{{ $team->id }}'
                                ? 'bg-pitch text-bone'
                                : 'bg-white text-pitch border border-pitch hover:bg-pitch hover:text-bone'"
                            class="px-3 py-1.5 rounded-md font-display font-bold uppercase text-[12px] tracking-wide-cta transition-all duration-fast flex items-center gap-1.5">
                        @if ($team->color)
                            <span class="w-2.5 h-2.5 rounded-full border border-line/50 shrink-0"
                                  style="background:{{ $team->color }}"></span>
                        @endif
                        {{ $team->name }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Buscador por nombre --}}
        <div class="mb-4"><x-search-input placeholder="Buscar jugador por nombre…" /></div>

        {{-- Tabla de estadísticas --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 overflow-x-auto">
            <table class="w-full text-left min-w-[700px]">
                <thead class="bg-pitch-mist border-b border-line">
                    <tr class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                        <th class="px-4 py-3 w-8">#</th>
                        <th class="px-4 py-3">Jugador</th>
                        <th class="px-4 py-3">Equipo</th>
                        <th class="px-3 py-3 text-center" title="Goles"><x-icon name="ball" class="w-4 h-4 inline text-pitch" /></th>
                        <th class="px-3 py-3 text-center" title="Asistencias"><x-icon name="assist" class="w-4 h-4 inline text-pitch" /></th>
                        <th class="px-3 py-3 text-center" title="Tarjetas amarillas"><x-icon name="card" class="w-3.5 h-3.5 inline text-warning" /></th>
                        <th class="px-3 py-3 text-center" title="Tarjetas rojas"><x-icon name="card" class="w-3.5 h-3.5 inline text-alerta" /></th>
                        <th class="px-3 py-3 text-center" title="Partidos jugados">PJ</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @foreach ($stats as $i => $stat)
                        @php $team = $stat->teamPlayer?->team; @endphp
                        <tr class="hover:bg-bone-soft transition-colors duration-fast"
                            x-show="(filterTeam === 'all' || filterTeam === '{{ $team?->id }}') && match(@js($stat->teamPlayer?->user?->name ?? '')) && (showAll || {{ $i }} < 10 || filterTeam !== 'all' || q !== '')"
                            x-transition>
                            <td class="px-4 py-3 font-mono text-[12px] text-ink-mute">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('torneos.estadisticas.jugador', [$tournament, $stat->teamPlayer]) }}"
                                   class="font-display font-bold text-pitch hover:underline">
                                    {{ $stat->teamPlayer?->user?->name ?? '—' }}
                                </a>
                                @if ($stat->teamPlayer?->jersey_number)
                                    <span class="font-mono text-[10px] text-ink-mute ml-1">#{{ $stat->teamPlayer->jersey_number }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    @if ($team?->color)
                                        <span class="w-2.5 h-2.5 rounded-full border border-line/50"
                                              style="background:{{ $team->color }}"></span>
                                    @endif
                                    @if ($team?->club)
                                        <a href="{{ route('torneos.clubes.show', $team->club) }}" class="text-[13px] text-ink hover:underline hover:text-pitch">{{ $team->name }}</a>
                                    @else
                                        <span class="text-[13px] text-ink">{{ $team?->name ?? '—' }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center font-mono font-bold text-[14px] text-pitch">{{ $stat->goals }}</td>
                            <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $stat->assists }}</td>
                            <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $stat->yellow_cards ?: '—' }}</td>
                            <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $stat->red_cards ?: '—' }}</td>
                            <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $stat->matches_played }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('torneos.estadisticas.jugador', [$tournament, $stat->teamPlayer]) }}"
                                   class="text-[12px] font-display font-semibold uppercase text-pitch hover:underline">
                                    Ver perfil
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($stats->count() > 10)
            <div class="mt-3 text-center" x-show="filterTeam === 'all' && q === ''">
                <button type="button" @click="showAll = !showAll"
                        class="font-mono text-[12px] uppercase tracking-wide-label text-pitch hover:underline font-bold"
                        x-text="showAll ? 'Ver menos' : 'Ver todos ({{ $stats->count() }} jugadores)'">
                </button>
            </div>
        @endif
    @endif
</div>
@endsection
