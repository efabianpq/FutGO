@extends('layouts.app')
@section('title', 'Mi Actividad')

@section('content')
@php
    $statusMeta = [
        'draft'       => ['Borrador',    'upcoming'],
        'open'        => ['Inscripción', 'win'],
        'in_progress' => ['En juego',    'live'],
        'finished'    => ['Finalizado',  'default'],
        'cancelled'   => ['Cancelado',   'default'],
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-8">
        <p class="eyebrow">Portal del jugador</p>
        <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Mi Actividad</h1>
        <p class="text-ink-soft text-[14px] mt-1">Tu resumen como jugador: torneos, partidos, estadísticas y disciplina.</p>
    </div>

    {{-- ══ Mis estadísticas ════════════════════════════════════════════════ --}}
    <section class="mb-8">
        <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Mis estadísticas</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach ([
                ['Partidos', $totals['matches_played'], 'text-pitch'],
                ['Goles', $totals['goals'], 'text-gol-deep'],
                ['Asistencias', $totals['assists'], 'text-pitch'],
                ['Amarillas', $totals['yellow_cards'], 'text-amber-500'],
                ['Rojas', $totals['red_cards'], 'text-alerta'],
                ['Minutos', $totals['minutes_played'], 'text-pitch'],
            ] as [$lbl, $val, $color])
                <div class="bg-white border border-line rounded-md shadow-card-2 p-4 text-center">
                    <p class="font-display font-extrabold text-3xl {{ $color }}">{{ $val }}</p>
                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-1">{{ $lbl }}</p>
                </div>
            @endforeach
        </div>

        @if ($statsByTournament->isNotEmpty())
            <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mt-4">
                <table class="w-full text-left">
                    <thead class="bg-pitch-mist border-b border-line">
                        <tr class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                            <th class="px-4 py-2.5">Torneo</th>
                            <th class="px-4 py-2.5 text-center">PJ</th>
                            <th class="px-4 py-2.5 text-center">Goles</th>
                            <th class="px-4 py-2.5 text-center">Asist.</th>
                            <th class="px-4 py-2.5 text-center">🟨</th>
                            <th class="px-4 py-2.5 text-center">🟥</th>
                            <th class="px-4 py-2.5 text-center">Min.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($statsByTournament as $s)
                            <tr class="hover:bg-bone-soft">
                                <td class="px-4 py-2.5 font-display font-semibold text-pitch text-[13px]">{{ $s->tournament?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-center font-mono">{{ $s->matches_played }}</td>
                                <td class="px-4 py-2.5 text-center font-mono font-bold text-gol-deep">{{ $s->goals }}</td>
                                <td class="px-4 py-2.5 text-center font-mono">{{ $s->assists }}</td>
                                <td class="px-4 py-2.5 text-center font-mono">{{ $s->yellow_cards }}</td>
                                <td class="px-4 py-2.5 text-center font-mono">{{ $s->red_cards }}</td>
                                <td class="px-4 py-2.5 text-center font-mono">{{ $s->minutes_played }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- ══ Mis torneos ═══════════════════════════════════════════════════ --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Mis torneos</p>

            @if ($activeTournaments->isEmpty() && $finishedTournaments->isEmpty())
                <p class="text-[13px] text-ink-mute italic">Todavía no jugás en ningún torneo.</p>
            @else
                @if ($activeTournaments->isNotEmpty())
                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol-deep mb-2">Activos</p>
                    <ul class="divide-y divide-line-soft mb-4">
                        @foreach ($activeTournaments as $t)
                            @php [$tl, $tv] = $statusMeta[$t->status] ?? [$t->status, 'default']; @endphp
                            <li class="py-2.5">
                                <a href="{{ route('torneos.hub', $t) }}" class="flex items-center justify-between gap-3 group">
                                    <span class="font-display font-semibold text-pitch text-[14px] truncate group-hover:underline">{{ $t->name }}</span>
                                    <x-badge :variant="$tv">{{ $tl }}</x-badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($finishedTournaments->isNotEmpty())
                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mb-2">Finalizados</p>
                    <ul class="divide-y divide-line-soft">
                        @foreach ($finishedTournaments as $t)
                            @php [$tl, $tv] = $statusMeta[$t->status] ?? [$t->status, 'default']; @endphp
                            <li class="py-2.5">
                                <a href="{{ route('torneos.hub', $t) }}" class="flex items-center justify-between gap-3 group">
                                    <span class="font-display font-semibold text-ink-soft text-[14px] truncate group-hover:underline">{{ $t->name }}</span>
                                    <x-badge :variant="$tv">{{ $tl }}</x-badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </section>

        {{-- ══ Mis partidos ══════════════════════════════════════════════════ --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Próximos partidos</p>
            <x-torneos.upcoming-matches :matches="$upcomingMatches" :limit="5" :show-phase="true" />

            <p class="font-display font-bold text-pitch uppercase text-[15px] mt-6 mb-3">Últimos resultados</p>
            @if ($recentResults->isEmpty())
                <p class="text-[13px] text-ink-mute italic">Todavía no hay resultados.</p>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($recentResults as $m)
                        <li class="py-2.5 flex items-center justify-between gap-3">
                            <span class="font-display font-semibold text-pitch text-[13px] truncate">
                                {{ $m->homeTeam?->name ?? '—' }}
                                <span class="font-mono text-pitch mx-1">{{ $m->home_score }}–{{ $m->away_score }}</span>
                                {{ $m->awayTeam?->name ?? '—' }}
                            </span>
                            <span class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute shrink-0">{{ $m->phase?->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- ══ Mis sanciones ════════════════════════════════════════════════════ --}}
    <section>
        <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Mis sanciones</p>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Suspensiones activas --}}
            <div class="bg-white border {{ $activeSuspensions->isNotEmpty() ? 'border-alerta/50' : 'border-line' }} rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold uppercase text-[13px] {{ $activeSuspensions->isNotEmpty() ? 'text-alerta' : 'text-pitch' }} mb-2">
                    🟥 Suspensiones activas
                </p>
                @if ($activeSuspensions->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Sin suspensiones vigentes. ¡Seguí así!</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($activeSuspensions as $tp)
                            <li class="text-[13px]">
                                <span class="font-display font-semibold text-pitch">{{ $tp->team?->name ?? 'Equipo' }}</span>
                                <span class="font-mono text-[11px] text-ink-mute block">{{ $tp->team?->tournament?->name }} · roja vigente</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Historial disciplinario --}}
            <div class="bg-white border border-line rounded-md shadow-card-2 p-5 lg:col-span-2">
                <p class="font-display font-bold text-pitch uppercase text-[13px] mb-2">Historial disciplinario</p>
                @if ($disciplinary->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Sin tarjetas registradas.</p>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($disciplinary as $ev)
                            <li class="py-2 flex items-center justify-between gap-3 text-[13px]">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span>{{ $ev->type === 'red_card' ? '🟥' : '🟨' }}</span>
                                    <span class="truncate">
                                        <span class="font-display font-semibold text-pitch">{{ $ev->teamPlayer?->team?->name ?? '—' }}</span>
                                        <span class="font-mono text-[11px] text-ink-mute">· {{ $ev->match?->phase?->tournament?->name }}</span>
                                    </span>
                                </span>
                                <span class="font-mono text-[11px] text-ink-mute shrink-0">min {{ $ev->minute ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
