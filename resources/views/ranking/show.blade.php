@extends('layouts.app')

@section('title', 'Auditoría — ' . $participant->name)

@php
    if (! function_exists('points_class')) {
        function points_class($p) {
            return match ((int) $p) {
                5 => 'bg-amber-100 text-amber-900 border-amber-400',
                3 => 'bg-emerald-100 text-emerald-900 border-emerald-400',
                2 => 'bg-blue-100 text-blue-900 border-blue-400',
                1 => 'bg-yellow-100 text-yellow-900 border-yellow-400',
                default => 'bg-gray-100 text-gray-700 border-gray-300',
            };
        }
    }

    // Totales agregados a partir de las fases ya filtradas (solo finished+points)
    $totalPts = 0;
    $totalExactos = 0;
    $totalJugados = 0;
    foreach ($phases as $phase) {
        foreach ($phase['rows'] as $r) {
            if ($r['prediction']) {
                $totalJugados++;
                $totalPts += (int) $r['points_earned'];
                if ((int) $r['points_earned'] === 5) {
                    $totalExactos++;
                }
            }
        }
    }
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">

    {{-- Botón "Volver al Ranking" prominente, sticky en mobile --}}
    <a href="{{ route('ranking.index') }}"
       class="inline-flex items-center gap-2 font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
        </svg>
        Volver al Ranking
    </a>

    {{-- Header con stats del participante --}}
    <div class="bg-white border border-line rounded-md shadow-card p-6 sm:p-8 mt-4 mb-8">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
            <div class="col-span-2 sm:col-span-2">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Participante</p>
                <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1 leading-tight">{{ $participant->name }}</h1>
            </div>
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Posición</p>
                <p class="font-display font-extrabold text-display-s sm:text-display-m mt-1">
                    @if ($ranking && $ranking->current_position === 1)
                        <span class="text-gol-deep">🥇 1</span>
                    @elseif ($ranking && $ranking->current_position === 2)
                        <span class="text-[#8a8a8a]">🥈 2</span>
                    @elseif ($ranking && $ranking->current_position === 3)
                        <span class="text-[#b87333]">🥉 3</span>
                    @else
                        <span class="text-ink">{{ $ranking->current_position ?? '—' }}</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Puntos</p>
                <p class="font-display font-extrabold text-display-s sm:text-display-m text-pitch mt-1">
                    {{ $ranking->total_points ?? 0 }}
                    <span class="text-body-s text-ink-soft">/ {{ $ranking->exact_predictions ?? 0 }}🎯</span>
                </p>
            </div>
        </div>
    </div>

    @if ($totalFinished === 0)
        <div class="bg-white border border-line rounded-md shadow-card p-10 sm:p-16 text-center">
            <div class="text-5xl mb-4">⏳</div>
            <p class="font-display font-bold text-display-s text-pitch uppercase">Aún no hay partidos finalizados para este participante</p>
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-3">La auditoría aparecerá cuando el administrador cargue los primeros resultados oficiales.</p>
        </div>
    @else
        @foreach ($phases as $phase)
            <section class="mb-8">
                <header class="flex items-end justify-between mb-3 pb-2 border-b-2 border-pitch">
                    <h2 class="font-display font-bold text-display-s text-pitch uppercase">{{ $phase['label'] }}</h2>
                    <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">{{ count($phase['rows']) }} partidos</span>
                </header>

                <div class="bg-white border border-line rounded-md shadow-card overflow-x-auto">
                    <table class="w-full min-w-[600px]">
                        <thead class="bg-pitch text-bone">
                            <tr class="font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                                <th class="px-4 py-2.5">#</th>
                                <th class="px-4 py-2.5">Partido</th>
                                <th class="px-4 py-2.5 text-right">Pronóstico</th>
                                <th class="px-4 py-2.5 text-right">Resultado</th>
                                <th class="px-4 py-2.5 text-right">Puntos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @foreach ($phase['rows'] as $row)
                                @php
                                    $pts = (int) $row['points_earned'];
                                    $badgeVariant = match ($pts) {
                                        5, 3 => 'win',
                                        2 => 'default',
                                        1 => 'win',
                                        default => 'upcoming',
                                    };
                                @endphp
                                <tr class="hover:bg-bone-soft transition-colors duration-fast">
                                    <td class="px-4 py-3 font-mono text-[11px] tracking-wide-eyebrow uppercase text-ink-mute whitespace-nowrap">#{{ $row['match_number'] }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-display font-bold text-body uppercase tracking-[.02em]">
                                            {{ $row['home_flag'] }} {{ $row['home_team'] }}
                                            <span class="text-ink-mute font-body normal-case">vs</span>
                                            {{ $row['away_flag'] }} {{ $row['away_team'] }}
                                        </p>
                                        <p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute mt-1">
                                            @if ($row['group_name']) Grupo {{ $row['group_name'] }} · @endif
                                            {{ $row['date_label'] }} GMT-5
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-display font-extrabold text-display-s whitespace-nowrap">
                                        @if ($row['prediction'])
                                            <span class="text-pitch">{{ $row['prediction'] }}</span>
                                        @else
                                            <span class="font-body font-normal text-body-s text-ink-mute italic">Sin pronóstico</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-display font-extrabold text-display-s text-ink whitespace-nowrap">{{ $row['official'] }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <x-badge :variant="$badgeVariant">{{ $pts }} pts</x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        {{-- ─── Resumen al final ─── --}}
        <section class="bg-pitch text-bone rounded-md shadow-card-2 p-6 sm:p-8 mt-2">
            <p class="font-mono text-[11px] tracking-wide-label uppercase opacity-70 mb-4">Resumen del participante</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70">Puntos totales</p>
                    <p class="font-display font-extrabold text-display-m sm:text-display-l text-gol leading-none mt-2">{{ $totalPts }}</p>
                </div>
                <div>
                    <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70">Exactos</p>
                    <p class="font-display font-extrabold text-display-m sm:text-display-l text-gol leading-none mt-2">{{ $totalExactos }}<span class="text-display-s opacity-70">🎯</span></p>
                </div>
                <div>
                    <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70">Partidos jugados</p>
                    <p class="font-display font-extrabold text-display-m sm:text-display-l text-bone leading-none mt-2">{{ $totalJugados }}<span class="text-display-s opacity-70"> / {{ $totalFinished }}</span></p>
                </div>
                <div>
                    <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70">Aprovechamiento</p>
                    <p class="font-display font-extrabold text-display-m sm:text-display-l text-bone leading-none mt-2">
                        {{ $totalFinished > 0 ? round($totalPts / ($totalFinished * 5) * 100) : 0 }}<span class="text-display-s opacity-70">%</span>
                    </p>
                </div>
            </div>
            <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-60 mt-5 leading-relaxed">
                Aprovechamiento = puntos obtenidos vs máximo teórico ({{ $totalFinished }} × 5 pts = {{ $totalFinished * 5 }} pts).
                Solo cuenta partidos finalizados con resultado oficial cargado.
            </p>
        </section>

        {{-- Botón "Volver al Ranking" duplicado al final, útil después de scroll --}}
        <div class="mt-6 flex justify-center">
            <a href="{{ route('ranking.index') }}"
               class="inline-flex items-center gap-2 font-display font-bold text-[13px] uppercase tracking-wide-cta px-5 py-2.5 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al Ranking
            </a>
        </div>
    @endif
</div>
@endsection
