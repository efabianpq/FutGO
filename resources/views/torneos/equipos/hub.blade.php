@extends('layouts.app')
@section('title', $team->name . ' · ' . $tournament->name)

@section('content')
@php
    $canManage = $isCaptain && $tournament->isOpen();
    $teamStatusMeta = [
        'pending'  => ['Pendiente aprobación', 'upcoming'],
        'approved' => ['Aprobado',             'win'],
        'rejected' => ['Rechazado',            'default'],
    ];
    [$tsLabel, $tsVariant] = $teamStatusMeta[$team->status] ?? [$team->status, 'default'];

    $playerStatusMeta = [
        'active'   => ['Activo',   'win'],
        'inactive' => ['Inactivo', 'default'],
        'pending'  => ['Pendiente','upcoming'],
        'rejected' => ['Rechazado','default'],
    ];
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('torneos.hub', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Mi equipo</span>
    </nav>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    {{-- ══ 1. INFORMACIÓN GENERAL ═══════════════════════════════════════════ --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 mb-6">
        <div class="flex items-center gap-4">
            {{-- Avatar del equipo: más pequeño en móvil (w-12) --}}
            @if ($team->shield_url)
                <img src="{{ $team->shield_url }}" alt="{{ $team->name }}"
                     class="w-12 sm:w-16 h-12 sm:h-16 rounded-full object-cover border border-line shrink-0">
            @else
                <span class="w-12 sm:w-16 h-12 sm:h-16 rounded-full border border-line shrink-0 flex items-center justify-center font-display font-extrabold text-pitch text-xl sm:text-2xl"
                      style="background:{{ $team->color ?? '#E8E8E8' }}">
                    {{ mb_strtoupper(mb_substr($team->name, 0, 1)) }}
                </span>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-display font-bold text-xl sm:text-display-s md:text-display-m text-pitch uppercase break-words">{{ $team->name }}</h1>
                    <x-badge :variant="$tsVariant">{{ $tsLabel }}</x-badge>
                </div>
                <p class="font-mono text-[12px] text-ink-mute mt-1">
                    Capitán: <span class="text-pitch font-semibold">{{ $team->captain?->name ?? '—' }}</span>
                </p>
            </div>
        </div>
    </div>

    {{-- ══ Estadísticas ═════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-6">
        @foreach ([
            ['PJ', $stats['played'], 'pitch'],
            ['PG', $stats['won'], 'gol'],
            ['PE', $stats['drawn'], 'pitch'],
            ['PP', $stats['lost'], 'alerta'],
            ['GF', $stats['goals_for'], 'pitch'],
            ['GC', $stats['goals_against'], 'pitch'],
        ] as [$lbl, $val, $accent])
            <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4
                {{ $accent === 'gol' ? 'border-l-gol' : ($accent === 'alerta' ? 'border-l-alerta' : 'border-l-pitch') }}">
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                <p class="font-display font-extrabold text-2xl mt-0.5
                    {{ $accent === 'gol' ? 'text-gol-deep' : ($accent === 'alerta' ? 'text-alerta' : 'text-pitch') }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ══ 2. PLANTILLA ═════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Plantilla en este torneo (snapshot) — la gestión es del equipo permanente --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
                <div class="bg-pitch-mist border-b border-line px-4 py-3 flex items-center justify-between gap-3">
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                        Plantilla en este torneo ({{ $activePlayers->count() }})
                    </p>
                    @if ($isCaptain && $team->club_id)
                        <a href="{{ route('torneos.clubes.manage', $team->club_id) }}"
                           class="font-display font-bold text-[12px] uppercase text-pitch hover:underline tracking-wide-cta">Gestionar equipo</a>
                    @endif
                </div>

                @if ($pendingPlayers->isNotEmpty())
                    <div class="border-b border-line-soft bg-bone-soft px-4 py-2">
                        <p class="font-mono text-[11px] text-ink-soft">{{ $pendingPlayers->count() }} jugador(es) pendiente(s) de aprobación del organizador para este torneo.</p>
                    </div>
                @endif

                @if ($activePlayers->isEmpty())
                    <div class="p-8 text-center text-ink-soft">Sin jugadores activos.</div>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($activePlayers as $tp)
                            @php [$psLabel, $psVariant] = $playerStatusMeta[$tp->status] ?? [$tp->status, 'default']; @endphp
                            <li class="flex items-center justify-between px-4 py-3 hover:bg-bone-soft">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="font-mono text-[13px] text-ink-mute w-8 text-right shrink-0">{{ $tp->jersey_number ? '#' . $tp->jersey_number : '—' }}</span>
                                    <x-avatar :user="$tp->user" :name="$tp->displayName()" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('torneos.estadisticas.jugador', [$tournament, $tp]) }}"
                                           class="font-semibold text-[14px] text-pitch hover:underline">{{ $tp->displayName() }}</a>
                                        <p class="font-mono text-[11px] text-ink-mute flex items-center gap-1.5">
                                            {{ $tp->position ?? '' }}
                                            @if ($tp->isCaptain())
                                                <x-badge variant="win">Capitán</x-badge>
                                            @elseif ($tp->status !== 'active')
                                                <x-badge :variant="$psVariant">{{ $psLabel }}</x-badge>
                                            @endif
                                            @if ($tp->isPorVerificar())
                                                <x-badge variant="upcoming">Por verificar</x-badge>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ══ 4. ÚLTIMOS RESULTADOS ════════════════════════════════════════ --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Últimos resultados</p>
                @if ($recentResults->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Sin partidos finalizados todavía.</p>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($recentResults->take(5) as $match)
                            @php
                                $isHome = $match->home_team_id === $team->id;
                                $rival  = $isHome ? $match->awayTeam : $match->homeTeam;
                                $teamScore  = $isHome ? $match->home_score : $match->away_score;
                                $rivalScore = $isHome ? $match->away_score : $match->home_score;
                                $outcome = match (true) {
                                    $match->winner_team_id === $team->id => ['V', 'text-gol-deep'],
                                    $match->winner_team_id === null      => ['E', 'text-ink-soft'],
                                    default                              => ['D', 'text-alerta'],
                                };
                            @endphp
                            <li class="py-2.5 flex items-center gap-3">
                                <span class="font-display font-extrabold text-[13px] w-5 {{ $outcome[1] }}">{{ $outcome[0] }}</span>
                                <span class="flex-1 font-display font-semibold text-pitch text-[13px] truncate">vs {{ $rival?->name ?? 'Por definir' }}</span>
                                <span class="font-display font-extrabold text-[16px] text-pitch">{{ $teamScore }} – {{ $rivalScore }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- ══ 3. PRÓXIMOS PARTIDOS ═════════════════════════════════════════ --}}
        <div class="space-y-6">
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Próximos partidos</p>
                <x-torneos.upcoming-matches :matches="$upcomingMatches" :team-id="$team->id" :limit="5" :show-phase="true" />

                @if ($isCaptain && $upcomingMatches->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-line-soft">
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mb-2">Convocatoria (capitán)</p>
                        <ul class="space-y-1.5">
                            @foreach ($upcomingMatches->take(5) as $m)
                                <li class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-[11px] text-ink-soft truncate">
                                        {{ $m->scheduled_at ? $m->scheduled_at->format('d/m') : '—' }} ·
                                        {{ $m->homeTeam?->name ?? '?' }} vs {{ $m->awayTeam?->name ?? '?' }}
                                    </span>
                                    <a href="{{ route('torneos.convocatoria.manage', [$tournament, $m]) }}"
                                       class="font-display font-bold text-[11px] uppercase text-pitch hover:underline tracking-wide-cta shrink-0">Convocar</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <div class="text-center">
                <x-btn :href="route('torneos.cronograma.team', [$tournament, $team])" variant="ghost" size="sm">Ver cronograma del equipo</x-btn>
            </div>
        </div>
    </div>
</div>
@endsection
