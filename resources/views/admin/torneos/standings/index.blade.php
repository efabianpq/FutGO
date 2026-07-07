@extends('layouts.app')
@section('title', 'Admin · Posiciones · ' . $tournament->name)

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ q: '', match(s){ return this.q === '' || s.toLowerCase().includes(this.q.toLowerCase()); } }">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Posiciones</span>
    </nav>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">{{ $tournament->name }}</p>
            <div class="flex items-center gap-2 mt-1">
                <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase">Posiciones</h1>
                <x-help-hint topic="admin.torneos.standings.index" />
            </div>
            @if ($tournament->tiebreaker_order)
                <p class="font-mono text-[11px] text-ink-mute mt-1">
                    Desempate: {{ implode(' → ', $tournament->tiebreaker_order) }}
                </p>
            @endif
        </div>
        <div class="flex gap-3">
            @if ($phases->isNotEmpty())
            <form method="POST" action="{{ route('admin.torneos.standings.recalculate', $tournament) }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 font-display font-bold uppercase text-[13px] tracking-wide-cta bg-pitch text-bone rounded-md hover:bg-pitch-deep transition-all duration-fast">
                    ↻ Recalcular
                </button>
            </form>
            @endif
            <a href="{{ route('admin.torneos.show', $tournament) }}"
               class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline self-center">
                ← Dashboard
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif

    @if ($phases->isEmpty() && $knockoutPhases->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">Este torneo no tiene posiciones ni llaves aún.</p>
            <p class="text-[13px] text-ink-mute mt-2">Generá el fixture primero desde el dashboard.</p>
        </div>
    @else
        @if ($phases->isNotEmpty())
        <div class="mb-5"><x-search-input placeholder="Buscar equipo por nombre…" /></div>
        @foreach ($phases as $phase)
            <div class="mb-8">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4 flex items-center gap-2">
                    {{ $phase->name }}
                    @if ($phase->is_active)
                        <x-badge variant="live">En curso</x-badge>
                    @else
                        <x-badge variant="default">Finalizada</x-badge>
                    @endif
                </p>

                @foreach ($phase->groups as $group)
                    <div class="mb-5">
                        <p class="font-mono text-[12px] uppercase tracking-wide-label text-ink-mute mb-2">
                            Grupo {{ $group->name }}
                        </p>

                        @if ($group->standings->isEmpty())
                            <div class="bg-bone-soft border border-line rounded-md p-4 text-center text-[13px] text-ink-mute">
                                Sin resultados registrados para este grupo.
                            </div>
                        @else
                            <div class="bg-white border border-line rounded-md shadow-card-2 overflow-x-auto">
                                <table class="w-full min-w-[560px] text-left">
                                    <thead class="bg-pitch-mist border-b border-line">
                                        <tr class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                                            <th class="px-3 py-3 w-8">#</th>
                                            <th class="px-4 py-3">Equipo</th>
                                            <th class="px-3 py-3 text-center" title="Partidos jugados">PJ</th>
                                            <th class="px-3 py-3 text-center" title="Partidos ganados">PG</th>
                                            <th class="px-3 py-3 text-center" title="Partidos empatados">PE</th>
                                            <th class="px-3 py-3 text-center" title="Partidos perdidos">PP</th>
                                            <th class="px-3 py-3 text-center" title="Goles a favor">GF</th>
                                            <th class="px-3 py-3 text-center" title="Goles en contra">GC</th>
                                            <th class="px-3 py-3 text-center" title="Diferencia de goles">DG</th>
                                            <th class="px-3 py-3 text-center font-bold text-pitch" title="Puntos">PTS</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line-soft">
                                        @foreach ($group->standings as $standing)
                                            @php
                                                $pos = $standing->position;
                                                // Clasificado: verde; eliminado: tenue
                                                if ($pos <= $classifiesPerGroup) {
                                                    $rowClass = 'bg-gol/5 border-l-2 border-gol';
                                                    $posClass = 'text-gol-deep font-bold';
                                                } else {
                                                    $rowClass = '';
                                                    $posClass = 'text-ink-mute';
                                                }
                                            @endphp
                                            <tr class="hover:bg-bone-soft transition-colors duration-fast {{ $rowClass }}"
                                                x-show="match(@js($standing->team?->name ?? ''))" x-cloak>
                                                <td class="px-3 py-3 font-mono text-[13px] {{ $posClass }}">{{ $pos }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        @if ($standing->team?->color)
                                                            <span class="w-3 h-3 rounded-full border border-line/50 shrink-0"
                                                                  style="background:{{ $standing->team->color }}"></span>
                                                        @endif
                                                        <span class="font-display font-semibold text-pitch text-[14px]">
                                                            {{ $standing->team?->name ?? '—' }}
                                                        </span>
                                                        @if ($pos <= $classifiesPerGroup)
                                                            <span class="font-mono text-[9px] uppercase tracking-wide-label text-gol-deep">Clasifica</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $standing->played }}</td>
                                                <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $standing->won }}</td>
                                                <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $standing->drawn }}</td>
                                                <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $standing->lost }}</td>
                                                <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $standing->goals_for }}</td>
                                                <td class="px-3 py-3 text-center font-mono text-[13px]">{{ $standing->goals_against }}</td>
                                                <td class="px-3 py-3 text-center font-mono text-[13px]
                                                    {{ $standing->goal_difference > 0 ? 'text-gol-deep' : ($standing->goal_difference < 0 ? 'text-alerta' : '') }}">
                                                    {{ $standing->goal_difference > 0 ? '+' : '' }}{{ $standing->goal_difference }}
                                                </td>
                                                <td class="px-3 py-3 text-center font-display font-extrabold text-[16px] text-pitch">
                                                    {{ $standing->points }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Leyenda --}}
                            <div class="flex items-center gap-4 mt-2 px-1">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 bg-gol/20 border-l-2 border-gol rounded-sm"></div>
                                    <span class="font-mono text-[11px] text-ink-mute">Clasifica (top {{ $classifiesPerGroup }})</span>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
        @endif

        {{-- Bracket eliminatorio --}}
        @if ($knockoutPhases->isNotEmpty())
            <div class="mt-8 bg-white border border-line rounded-md shadow-card-2 p-5">
                @include('torneos._bracket', ['knockoutPhases' => $knockoutPhases])
            </div>
        @endif
    @endif

    {{-- Info de sistema de puntos --}}
    <div class="bg-bone-soft border border-line rounded-md p-4 mt-6">
        <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mb-2">Sistema de puntuación</p>
        <div class="flex gap-6 font-mono text-[13px]">
            <span><strong class="text-pitch">Victoria:</strong> {{ $tournament->points_win ?? 3 }} pts</span>
            <span><strong class="text-pitch">Empate:</strong> {{ $tournament->points_draw ?? 1 }} pts</span>
            <span><strong class="text-pitch">Derrota:</strong> {{ $tournament->points_loss ?? 0 }} pts</span>
        </div>
    </div>
</div>
@endsection
