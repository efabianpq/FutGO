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

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ credentialOpen: false }">

    {{-- ── Modal credencial (H16): QR + identificador FUTGO ──────────────────── --}}
    <div x-show="credentialOpen" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
         @click.self="credentialOpen = false" @keydown.escape.window="credentialOpen = false">
        <div class="bg-white rounded-lg shadow-modal w-full max-w-sm overflow-hidden">
            <div class="bg-pitch px-6 py-4 flex items-center justify-between">
                <p class="font-display font-extrabold text-bone uppercase text-[15px]">Mi credencial</p>
                <button type="button" @click="credentialOpen = false" class="text-bone/80 hover:text-bone text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 flex flex-col items-center text-center">
                <div class="bg-white border border-line rounded-md p-2" style="width:220px;height:220px">
                    <div class="w-full h-full [&>svg]:w-full [&>svg]:h-full">{!! $credentialQrSvg !!}</div>
                </div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-4">Identificador FUTGO</p>
                <p class="font-mono font-bold text-2xl text-pitch mt-1 tracking-wider">{{ $user->futgo_id }}</p>
                <p class="text-[12px] text-ink-mute mt-3 leading-relaxed">
                    Presentá este código al árbitro para validar tu identidad.
                </p>
            </div>
        </div>
    </div>

    {{-- Encabezado: hoja de vida --}}
    {{-- Móvil: avatar+nombre en fila (sm avatar), botones debajo.
         Desktop: fila única con xl avatar + texto flex-1 + botones a la derecha. --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 mb-6 flex flex-wrap sm:flex-nowrap sm:items-center gap-4 sm:gap-5">
        <x-avatar :user="$user" size="lg" class="sm:hidden shrink-0" />
        <x-avatar :user="$user" size="xl" class="hidden sm:block shrink-0" />
        <div class="min-w-0 flex-1">
            <p class="eyebrow">Hoja de vida deportiva</p>
            <h1 class="font-display font-bold text-2xl sm:text-display-s md:text-display-m text-pitch uppercase mt-1 break-words">{{ $user->name }}</h1>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                {{ $careerStat->tournaments_count }} torneo(s) · {{ $careerStat->teams_count }} equipo(s)
            </p>
        </div>
        <div class="flex flex-row sm:flex-col gap-2 w-full sm:w-auto shrink-0">
            <x-btn type="button" variant="primary" size="sm" x-on:click="credentialOpen = true">Mi credencial</x-btn>
            <x-btn :href="route('profile.show')" variant="ghost" size="sm">Editar perfil / foto</x-btn>
        </div>
    </div>

    {{-- ══ 1 · Acumulado histórico (todos los torneos) ═══════════════════════ --}}
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Acumulado histórico (todos los torneos)</p>
    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-3">
        @foreach ([
            ['PJ', $careerStat->matches_played, 'pitch'],
            ['Goles', $careerStat->goals, 'gol'],
            ['Asist.', $careerStat->assists, 'pitch'],
            ['MVP', $careerStat->mvps, 'gol'],
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

    {{-- ══ 1b · Actividad social: partidos totales, presentación, amistosos ══ --}}
    @php $rec = $socialMetrics['friendly_record']; @endphp
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Actividad (torneos + amistosos)</p>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
        <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4 border-l-pitch">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Partidos totales</p>
            <p class="font-display font-extrabold text-2xl mt-0.5 text-pitch">{{ $socialMetrics['total_matches'] }}</p>
            <p class="font-mono text-[10px] text-ink-mute">{{ $socialMetrics['tournament_matches'] }} torneo · {{ $socialMetrics['friendlies_played'] }} amistoso</p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4 border-l-gol">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Presentación</p>
            <p class="font-display font-extrabold text-2xl mt-0.5 text-gol-deep">{{ $socialMetrics['presentation_pct'] !== null ? $socialMetrics['presentation_pct'] . '%' : '—' }}</p>
            <p class="font-mono text-[10px] text-ink-mute">{{ $socialMetrics['accepted_callups'] }} convocatoria(s) aceptada(s)</p>
        </div>
        <div class="bg-bone-soft border border-line rounded-md p-3 text-center">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Récord amistosos</p>
            <p class="font-display font-bold text-xl mt-0.5 text-pitch">{{ $rec['won'] }}V · {{ $rec['drawn'] }}E · {{ $rec['lost'] }}D</p>
            <p class="font-mono text-[10px] text-ink-mute">{{ $rec['gf'] }} GF · {{ $rec['ga'] }} GC</p>
        </div>
        <a href="{{ route('social.amistosos.index') }}" class="bg-pitch text-bone rounded-md p-3 text-center flex flex-col items-center justify-center hover:bg-pitch-deep transition-all duration-fast">
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-bone/70">Gestionar</p>
            <p class="font-display font-bold text-[15px] mt-0.5">Mis amistosos →</p>
        </a>
    </div>

    {{-- ══ 1c · Amistosos jugados ════════════════════════════════════════════ --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-8">
        <div class="bg-pitch-mist border-b border-line px-4 py-3 flex items-center justify-between">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Amistosos ({{ $friendlies->count() }})</p>
        </div>
        @if ($friendlies->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">Todavía no jugaste amistosos. <a href="{{ route('social.oportunidades.index', ['tipo' => 'BUSCAR_RIVAL']) }}" class="text-pitch underline">Buscá un rival</a>.</div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($friendlies as $row)
                    <li class="px-4 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-pitch text-[14px] truncate">
                                {{ $row->club?->name }} <span class="text-ink-mute font-normal">vs</span> {{ $row->opponent?->name ?? '—' }}
                            </p>
                            <p class="font-mono text-[11px] text-ink-mute">{{ $row->date?->format('d/m/Y') ?? 'Sin fecha' }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-display font-extrabold text-pitch text-[15px]">{{ $row->for }}–{{ $row->against }}</span>
                            <span class="px-2 py-0.5 rounded-pill font-display font-bold text-[10px] uppercase
                                {{ $row->outcome === 'V' ? 'bg-gol/20 text-pitch-deep' : ($row->outcome === 'D' ? 'bg-alerta/15 text-alerta-deep' : 'bg-bone-soft text-ink-mute') }}">
                                {{ $row->outcome === 'V' ? 'G' : ($row->outcome === 'D' ? 'P' : 'E') }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ══ 1d · Jugué con vos (S2-A): jugadores con quienes compartí cancha ══ --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-8"
             x-data="{ showAll: false }">
        <div class="bg-pitch-mist border-b border-line px-4 py-3 flex items-center justify-between">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Jugué con ({{ $playedWithTotal }})</p>
            <span class="font-mono text-[10px] text-ink-mute">Torneos + amistosos</span>
        </div>
        @if ($playedWith->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">
                Todavía no compartiste cancha con otros jugadores registrados. Se completa solo cuando jugás partidos.
            </div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($playedWithFull as $i => $row)
                    <li class="px-4 py-3 flex items-center gap-3" @if ($i >= 4) x-show="showAll" x-cloak @endif>
                        <x-avatar :name="$row->user->name" :src="$row->user->avatar_url" size="sm" class="shrink-0" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('social.player.show', $row->user->futgo_id) }}" class="font-display font-semibold text-pitch text-[14px] hover:underline truncate block">{{ $row->user->name }}</a>
                            <p class="font-mono text-[11px] text-ink-mute">
                                {{ $row->shared }} {{ Str::plural('partido', $row->shared) }} juntos
                                @if ($row->user->city) · {{ $row->user->city }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('social.oportunidades.create', ['tipo' => 'BUSCAR_RIVAL', 'target' => $row->user->futgo_id]) }}"
                               class="font-display font-bold text-[11px] uppercase text-gol-deep hover:underline tracking-wide-cta" title="Retar a un amistoso">Retar</a>
                            <span class="text-line">·</span>
                            <a href="{{ route('social.oportunidades.create', ['tipo' => 'BUSCAR_JUGADOR', 'target' => $row->user->futgo_id]) }}"
                               class="font-display font-bold text-[11px] uppercase text-pitch hover:underline tracking-wide-cta" title="Invitar a mi equipo">Invitar</a>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($playedWithTotal > 4)
                <div class="px-4 py-3 border-t border-line-soft text-center">
                    <button type="button" @click="showAll = !showAll"
                            class="font-mono text-[11px] uppercase tracking-wide-label text-pitch hover:underline font-bold"
                            x-text="showAll ? 'Ver menos' : 'Ver más ({{ $playedWithTotal - 4 }} más)'">
                    </button>
                </div>
            @endif
        @endif
    </section>

    {{-- ══ 2 · Mis equipos  +  3 · Mis torneos ═══════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
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
    </div>

    {{-- ══ 4 · Próximos partidos  +  5 · Últimos resultados ══════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Próximos partidos</p>
            </div>
            @if ($upcomingMatches->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin partidos próximos.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($upcomingMatches as $m)
                        @php $cu = $myCallUps->get($m->id); @endphp
                        <li class="px-4 py-3">
                            <p class="font-display font-semibold text-pitch text-[14px] truncate">
                                {{ $m->homeTeam?->name ?? 'Por definir' }} vs {{ $m->awayTeam?->name ?? 'Por definir' }}
                            </p>
                            <p class="font-mono text-[11px] text-ink-mute">
                                {{ $m->phase?->tournament?->name }} · {{ $m->scheduled_at ? $m->scheduled_at->format('d/m H:i') : 'Sin fecha' }}
                            </p>
                            @if ($cu)
                                <div class="mt-2 flex items-center gap-2 flex-wrap">
                                    @if ($cu->status === 'confirmado')
                                        <x-badge variant="win">Asistencia confirmada</x-badge>
                                    @elseif ($cu->status === 'declinado')
                                        <x-badge variant="default">Declinaste</x-badge>
                                    @else
                                        <span class="font-mono text-[11px] text-gol-deep">¡Estás convocado!</span>
                                    @endif

                                    @if (! $m->isFinished() && $cu->status !== 'confirmado')
                                        <form method="POST" action="{{ route('torneos.convocatoria.respond', [$m->phase->tournament, $m]) }}" class="inline">
                                            @csrf <input type="hidden" name="response" value="confirmado">
                                            <button type="submit" class="font-display font-bold text-[12px] uppercase text-gol-deep hover:underline tracking-wide-cta">Confirmar</button>
                                        </form>
                                    @endif
                                    @if (! $m->isFinished() && $cu->status !== 'declinado')
                                        <form method="POST" action="{{ route('torneos.convocatoria.respond', [$m->phase->tournament, $m]) }}" class="inline">
                                            @csrf <input type="hidden" name="response" value="declinado">
                                            <button type="submit" class="font-display font-semibold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">Declinar</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
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

    {{-- ══ 6 · Fair Play y Disciplina (H13: consolidado) ═════════════════════ --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-6">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Fair Play y Disciplina</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3">
            {{-- Score Fair Play --}}
            <div class="p-5 text-center border-b sm:border-b-0 sm:border-r border-line-soft">
                @php $fp = $fairPlay?->score ?? 100; @endphp
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Fair Play</p>
                <p class="font-display font-extrabold text-5xl mt-2 {{ $fp >= 90 ? 'text-gol-deep' : ($fp < 60 ? 'text-alerta' : 'text-pitch') }}">{{ $fp }}</p>
                <p class="font-mono text-[11px] text-ink-mute">/ 100</p>
                <div class="flex justify-center gap-4 mt-3 text-[12px] text-ink-soft">
                    <span>🟨 {{ $fairPlay?->yellow_cards ?? 0 }}</span>
                    <span>🟥 {{ $fairPlay?->red_cards ?? 0 }}</span>
                    <span>🚫 {{ $fairPlay?->absences ?? 0 }}</span>
                </div>
            </div>

            {{-- Detalle disciplinario --}}
            <div class="sm:col-span-2">
                @if ($activeSuspensions->isNotEmpty())
                    <div class="bg-alerta/10 border-b border-line px-4 py-3">
                        <p class="font-display font-semibold text-alerta text-[13px]">Tenés {{ $activeSuspensions->count() }} suspensión(es) vigente(s) por tarjeta roja.</p>
                    </div>
                @endif
                @if ($disciplinary->isEmpty())
                    <div class="p-6 text-center text-ink-soft text-[14px]">Sin tarjetas registradas. ¡Juego limpio!</div>
                @else
                    <ul class="divide-y divide-line-soft max-h-72 overflow-y-auto">
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
            </div>
        </div>
    </section>

    {{-- ══ 7 · Mi historial (H14: consolidado por temporada) ═════════════════ --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Mi historial</p>
        </div>
        @if ($statsByTournament->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">Sin estadísticas registradas todavía.</div>
        @else
            @php
                // Agrupa el detalle por torneo en temporadas (año del torneo).
                $historyBySeason = $statsByTournament->groupBy(function ($s) {
                    $date = $s->tournament?->starts_at ?? $s->tournament?->created_at;
                    return $date ? $date->format('Y') : 'Sin fecha';
                })->sortKeysDesc();
            @endphp
            <div class="divide-y divide-line">
                @foreach ($historyBySeason as $season => $rows)
                    @php
                        $sMatches = $rows->sum('matches_played');
                        $sGoals   = $rows->sum('goals');
                        $sAssists = $rows->sum('assists');
                        $sMvps    = $rows->sum('mvps');
                    @endphp
                    <div>
                        {{-- Encabezado de temporada --}}
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-bone-soft">
                            <p class="font-display font-extrabold text-pitch text-[15px]">Temporada {{ $season }}</p>
                            <p class="font-mono text-[11px] text-ink-mute">
                                {{ $sMatches }} PJ · {{ $sGoals }} G · {{ $sAssists }} A · {{ $sMvps }} MVP
                            </p>
                        </div>
                        {{-- Detalle por torneo de la temporada --}}
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[560px] text-left">
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
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line-soft">
                                    @foreach ($rows as $s)
                                        <tr class="hover:bg-bone-soft text-[13px]">
                                            <td class="px-4 py-3 font-display font-semibold text-pitch">{{ $s->tournament?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-ink-soft">{{ $s->teamPlayer?->team?->name ?? '—' }}</td>
                                            <td class="px-3 py-3 text-center font-mono">{{ $s->matches_played }}</td>
                                            <td class="px-3 py-3 text-center font-mono font-bold text-gol-deep">{{ $s->goals }}</td>
                                            <td class="px-3 py-3 text-center font-mono">{{ $s->assists }}</td>
                                            <td class="px-3 py-3 text-center font-mono">{{ $s->mvps }}</td>
                                            <td class="px-3 py-3 text-center font-mono">{{ $s->yellow_cards }}</td>
                                            <td class="px-3 py-3 text-center font-mono">{{ $s->red_cards }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
