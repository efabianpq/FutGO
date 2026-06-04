@extends('layouts.app')
@section('title', 'Mi carrera · ' . $user->name)

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

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Encabezado: hoja de vida --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6 flex flex-wrap items-center gap-5">
        <x-avatar :user="$user" size="xl" />
        <div class="min-w-0 flex-1">
            <p class="eyebrow">🪪 Hoja de vida deportiva</p>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1 break-words">{{ $user->name }}</h1>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                {{ $careerStat->tournaments_count }} torneo(s) · {{ $careerStat->teams_count }} equipo(s)
            </p>
        </div>
        <x-btn :href="route('profile.show')" variant="ghost" size="sm">Editar perfil / foto</x-btn>
    </div>

    {{-- Acumulado total --}}
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Acumulado histórico (todos los torneos)</p>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-8">
        @foreach ([
            ['PJ', $careerStat->matches_played, 'pitch'],
            ['Goles', $careerStat->goals, 'gol'],
            ['Asist.', $careerStat->assists, 'pitch'],
            ['MVP', $careerStat->mvps, 'gol'],
            ['Min', $careerStat->minutes_played, 'pitch'],
            ['Vallas 0', $careerStat->clean_sheets, 'pitch'],
        ] as [$lbl, $val, $accent])
            <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4
                {{ $accent === 'gol' ? 'border-l-gol' : 'border-l-pitch' }}">
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                <p class="font-display font-extrabold text-2xl mt-0.5 {{ $accent === 'gol' ? 'text-gol-deep' : 'text-pitch' }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-8">
        @foreach ([
            ['Victorias', $careerStat->wins],
            ['Empates', $careerStat->draws],
            ['Derrotas', $careerStat->losses],
            ['Amarillas', $careerStat->yellow_cards],
            ['Rojas', $careerStat->red_cards],
        ] as [$lbl, $val])
            <div class="bg-bone-soft border border-line rounded-md p-3 text-center">
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                <p class="font-display font-bold text-xl mt-0.5 text-pitch">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Mis torneos --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Mis torneos ({{ $tournaments->count() }})</p>
            </div>
            @if ($tournaments->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Todavía no participaste en ningún torneo.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($tournaments as $row)
                        @php [$lbl, $variant] = $statusMeta[$row['tournament']->status] ?? [$row['tournament']->status, 'default']; @endphp
                        <li class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('torneos.hub', $row['tournament']) }}" class="font-display font-semibold text-pitch text-[14px] hover:underline truncate block">{{ $row['tournament']->name }}</a>
                                <p class="font-mono text-[11px] text-ink-mute">{{ $row['team']->name }}</p>
                            </div>
                            <x-badge :variant="$variant">{{ $lbl }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Mis equipos --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Mis equipos ({{ $clubs->count() }})</p>
            </div>
            @if ($clubs->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin equipos todavía.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($clubs as $team)
                        <li class="px-4 py-3 flex items-center gap-3">
                            <x-avatar :name="$team->name" :src="$team->shieldUrl()" size="sm" />
                            <div class="min-w-0 flex-1">
                                @if ($team->club_id)
                                    <a href="{{ route('torneos.clubes.show', $team->club_id) }}" class="font-display font-semibold text-pitch text-[14px] hover:underline truncate block">{{ $team->name }}</a>
                                @else
                                    <p class="font-display font-semibold text-pitch text-[14px] truncate">{{ $team->name }}</p>
                                @endif
                                <p class="font-mono text-[11px] text-ink-mute">{{ $team->tournament?->name }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Mi historial (detalle por torneo) --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mt-6">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Mi historial</p>
        </div>
        @if ($statsByTournament->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">Sin estadísticas registradas todavía.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead>
                        <tr class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute border-b border-line-soft">
                            <th class="px-4 py-2">Torneo</th>
                            <th class="px-4 py-2">Equipo</th>
                            <th class="px-3 py-2 text-center">PJ</th>
                            <th class="px-3 py-2 text-center">Goles</th>
                            <th class="px-3 py-2 text-center">Asist.</th>
                            <th class="px-3 py-2 text-center">MVP</th>
                            <th class="px-3 py-2 text-center">🟨</th>
                            <th class="px-3 py-2 text-center">🟥</th>
                            <th class="px-3 py-2 text-center">Min</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($statsByTournament as $s)
                            <tr class="hover:bg-bone-soft text-[13px]">
                                <td class="px-4 py-3 font-display font-semibold text-pitch">{{ $s->tournament?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ $s->teamPlayer?->team?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $s->matches_played }}</td>
                                <td class="px-3 py-3 text-center font-mono font-bold text-gol-deep">{{ $s->goals }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $s->assists }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $s->mvps }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $s->yellow_cards }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $s->red_cards }}</td>
                                <td class="px-3 py-3 text-center font-mono">{{ $s->minutes_played }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ══ Mi actividad (próximos / resultados / disciplina) ═══════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Próximos partidos</p>
            </div>
            @if ($upcomingMatches->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin partidos próximos.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($upcomingMatches as $m)
                        <li class="px-4 py-3">
                            <p class="font-display font-semibold text-pitch text-[14px] truncate">
                                {{ $m->homeTeam?->name ?? 'Por definir' }} vs {{ $m->awayTeam?->name ?? 'Por definir' }}
                            </p>
                            <p class="font-mono text-[11px] text-ink-mute">
                                {{ $m->phase?->tournament?->name }} · {{ $m->scheduled_at ? $m->scheduled_at->format('d/m H:i') : 'Sin fecha' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Últimos resultados</p>
            </div>
            @if ($recentResults->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin resultados todavía.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($recentResults as $m)
                        <li class="px-4 py-3 flex items-center justify-between gap-3">
                            <span class="font-display font-semibold text-pitch text-[13px] truncate">
                                {{ $m->homeTeam?->name ?? '—' }} vs {{ $m->awayTeam?->name ?? '—' }}
                            </span>
                            <span class="font-display font-extrabold text-pitch text-[15px]">{{ $m->home_score }}–{{ $m->away_score }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Disciplina --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mt-6">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Disciplina</p>
        </div>
        @if ($activeSuspensions->isNotEmpty())
            <div class="bg-alerta/10 border-b border-line px-4 py-3">
                <p class="font-display font-semibold text-alerta text-[13px]">Tenés {{ $activeSuspensions->count() }} suspensión(es) vigente(s) por tarjeta roja.</p>
            </div>
        @endif
        @if ($disciplinary->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">Sin tarjetas registradas. ¡Juego limpio!</div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($disciplinary as $ev)
                    <li class="px-4 py-3 flex items-center gap-3">
                        <span class="text-lg">{{ $ev->type === 'red_card' ? '🟥' : '🟨' }}</span>
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-pitch text-[13px]">
                                {{ $ev->type === 'red_card' ? 'Roja' : 'Amarilla' }}
                                @if ($ev->minute) · min {{ $ev->minute }} @endif
                            </p>
                            <p class="font-mono text-[11px] text-ink-mute truncate">
                                {{ $ev->match?->phase?->tournament?->name }} ·
                                {{ $ev->match?->homeTeam?->name ?? '—' }} vs {{ $ev->match?->awayTeam?->name ?? '—' }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
