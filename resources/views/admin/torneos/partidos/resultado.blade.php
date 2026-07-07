@extends('layouts.app')
@section('title', 'Admin · Planilla · Partido #' . $match->match_number)

@section('content')
@include('admin.torneos._nav')

@php
// Convocatoria: si la planilla nunca se guardó (sin lineups), nadie sale
// marcado por defecto — solo se pre-cargan los confirmados en convocatoria
// previa (si existe). Quien diligencia la planilla marca jugó/titular.
// En re-edición se respeta lo guardado.
$freshSheet = $existingLineups->isEmpty();

// Limitación #6: si hay convocatoria previa cargada para el equipo, la planilla
// arranca marcando solo a los jugadores confirmados (no todo el plantel activo).
$confirmedCallUpIds ??= collect();
$teamsWithCallUps ??= collect();

// Eventos existentes agrupados por jugador (para re-edición de un partido en vivo).
$eventsByPlayer = $match->events->groupBy('team_player_id');

$buildPlayers = function ($players, $teamId, $captainId) use ($existingLineups, $eventsByPlayer, $freshSheet, $confirmedCallUpIds, $teamsWithCallUps) {
    return $players->map(function ($p) use ($existingLineups, $eventsByPlayer, $freshSheet, $confirmedCallUpIds, $teamsWithCallUps, $teamId, $captainId) {
        $evs         = $eventsByPlayer->get($p->id, collect());
        $goalMinutes = $evs->where('type', 'goal')
            ->pluck('minute')
            ->map(fn ($m) => $m !== null ? (string) $m : '')
            ->values()->all();

        $playedOnFreshSheet = $teamsWithCallUps->contains($teamId)
            ? $confirmedCallUpIds->contains($p->id)
            : false;

        return [
            'id'          => $p->id,
            'name'        => $p->user?->name ?? 'Jugador',
            'number'      => $p->jersey_number,
            'position'    => $p->position,
            'is_captain'  => $captainId && $p->user_id === $captainId,
            'team_id'     => $teamId,
            'played'      => $freshSheet ? $playedOnFreshSheet : $existingLineups->has($p->id),
            'starter'     => $existingLineups->has($p->id) ? (bool) $existingLineups[$p->id]->started : false,
            'goals'       => $evs->where('type', 'goal')->count(),
            'assists'     => $evs->where('type', 'assist')->count(),
            'yellow'      => $evs->where('type', 'yellow_card')->count(),
            'red'         => $evs->where('type', 'red_card')->count(),
            'goalMinutes' => $goalMinutes,
        ];
    })->values()->all();
};

$allPlayersData = array_merge(
    $buildPlayers($homePlayers, $match->home_team_id, $match->homeTeam?->captain_user_id),
    $buildPlayers($awayPlayers, $match->away_team_id, $match->awayTeam?->captain_user_id),
);

$ms     = $match->match_sheet ?? [];
$hSheet = $ms['home'] ?? [];
$aSheet = $ms['away'] ?? [];

$categoryLabels = [
    'libre' => 'Libre', 'veteranos' => 'Veteranos', 'sub15' => 'Sub-15', 'sub17' => 'Sub-17',
    'sub20' => 'Sub-20', 'femenino' => 'Femenino', 'mixto' => 'Mixto',
];

$formInit = [
    'homeName'    => $match->homeTeam?->name ?? 'Local',
    'awayName'    => $match->awayTeam?->name ?? 'Visitante',
    'homeColor'   => $match->homeTeam?->color ?? '#1a1a2e',
    'awayColor'   => $match->awayTeam?->color ?? '#4a4a6a',
    'homeTeamId'  => $match->home_team_id,
    'awayTeamId'  => $match->away_team_id,
    'players'     => $allPlayersData,
    'isWalkover'  => (bool) $match->is_walkover,
    'walkoverWinner' => $match->is_walkover && $match->winner_team_id
        ? ($match->winner_team_id === $match->home_team_id ? 'home' : 'away')
        : '',
    'walkoverWin' => 3,
    'isKnockout'  => $match->phase?->type === 'knockout',
    'homePenalties' => $match->home_penalties !== null ? (int) $match->home_penalties : '',
    'awayPenalties' => $match->away_penalties !== null ? (int) $match->away_penalties : '',
    'mvpEnabled'  => $mvpEnabled,
    'mvp'         => $match->mvp_team_player_id ? (string) $match->mvp_team_player_id : '',
];
@endphp

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <a href="{{ route('admin.torneos.partidos.index', $tournament) }}" class="hover:text-pitch">Partidos</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Partido #{{ $match->match_number }}</span>
    </nav>

    @php
        $matchStatusMeta = [
            'scheduled' => ['Programado', 'upcoming'],
            'live'      => ['En vivo',   'live'],
            'finished'  => ['Finalizado','win'],
            'postponed' => ['Postpuesto','default'],
        ];
        [$mStatusLabel, $mStatusVariant] = $matchStatusMeta[$match->status] ?? [$match->status, 'default'];
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">{{ $tournament->name }}</p>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase">Planilla del Partido</h1>
                <x-help-hint topic="torneos.partidos.resultado" />
                <x-badge :variant="$mStatusVariant">{{ $mStatusLabel }}</x-badge>
                @if ($match->is_walkover)
                    <x-badge variant="default">W.O.</x-badge>
                @endif
            </div>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                {{ $match->phase->name }} · Partido #{{ $match->match_number }}
            </p>
        </div>
        <x-btn :href="route('admin.torneos.partidos.pdf', [$tournament, $match])" variant="ghost" size="sm"><x-icon name="download" class="w-4 h-4 inline -mt-0.5" /> Descargar PDF</x-btn>
    </div>

    @unless ($canEdit)
        <div class="mb-6 bg-pitch-mist border border-line rounded-md px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">
                Este partido está <strong class="text-pitch">finalizado</strong>. La planilla quedó como documento oficial.
                Para corregir datos, anula el resultado y vuelve a cargarlo.
            </p>
            <form method="POST" action="{{ route('admin.torneos.partidos.destroy', [$tournament, $match]) }}"
                  x-data @submit.prevent="if (confirm('¿Anular el resultado para editar la planilla?')) $el.submit()">
                @csrf @method('DELETE')
                <x-btn type="submit" variant="danger" size="sm">Anular para editar</x-btn>
            </form>
        </div>
    @endunless

    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md">
            <p class="font-display font-bold text-[14px] mb-1">Errores en el formulario:</p>
            <ul class="list-disc list-inside text-[13px] space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.torneos.partidos.store', [$tournament, $match]) }}"
          x-data="resultadoForm({{ json_encode($formInit) }})"
          @submit.prevent="submit($el)">
        @csrf

        {{-- ─── Popup: minutos de goles (opcional) ─── --}}
        <div x-show="popup.open" x-cloak
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
             @click.self="closePopup()">
            <div class="bg-white rounded-lg shadow-modal w-full max-w-xs p-5">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-display font-bold text-pitch text-[15px]">⏱ Minutos de goles</p>
                        <p class="font-mono text-[11px] text-ink-mute" x-text="popup.playerName"></p>
                    </div>
                    <button type="button" @click="closePopup()" class="text-ink-mute hover:text-pitch text-xl leading-none">&times;</button>
                </div>
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mb-3">Opcional — deja en blanco si no lo sabes</p>
                <div class="space-y-2">
                    <template x-for="(min, i) in popup.minutes" :key="i">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-pitch text-bone text-[11px] font-bold flex items-center justify-center shrink-0" x-text="i + 1"></span>
                            <input type="number" min="1" max="120" x-model="popup.minutes[i]" placeholder="min."
                                   class="flex-1 border border-line rounded-md px-3 py-2 text-[14px] text-center focus:outline-none focus:border-pitch">
                            <span class="font-mono text-[11px] text-ink-mute">min</span>
                        </div>
                    </template>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" @click="closePopup()"
                            class="flex-1 border border-line rounded-md py-2 text-[13px] font-display font-semibold text-ink-mute hover:bg-bone-soft">Cancelar</button>
                    <button type="button" @click="saveMinutes()"
                            class="flex-1 bg-pitch text-bone rounded-md py-2 text-[13px] font-display font-semibold hover:bg-pitch-deep">Listo</button>
                </div>
            </div>
        </div>

        {{-- ─── Datos del partido (contexto) ─── --}}
        <div class="bg-pitch-mist border border-line rounded-md p-5 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[13px] mb-3">Datos del partido</p>
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-2 text-[13px]">
                <div><dt class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Torneo</dt><dd class="font-semibold text-pitch truncate">{{ $tournament->name }}</dd></div>
                <div><dt class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Categoría</dt><dd class="font-semibold text-pitch">{{ $categoryLabels[$tournament->category] ?? ucfirst($tournament->category ?? '—') }}</dd></div>
                <div><dt class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Fase</dt><dd class="font-semibold text-pitch">{{ $match->phase->name }}</dd></div>
                <div><dt class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Grupo</dt><dd class="font-semibold text-pitch">{{ $match->group?->name ?? '—' }}</dd></div>
                <div><dt class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Jornada / J. N°</dt><dd class="font-semibold text-pitch">#{{ $match->match_number }}</dd></div>
                <div><dt class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Escenario</dt><dd class="font-semibold text-pitch truncate">{{ $match->venue ?? '—' }}</dd></div>
                <div class="col-span-2"><dt class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Fecha y hora</dt><dd class="font-semibold text-pitch">{{ $match->scheduled_at?->format('d/m/Y H:i') ?? 'Sin programar' }}</dd></div>
            </dl>
            <p class="text-[11px] text-ink-mute mt-2">Escenario y fecha se editan desde <a href="{{ route('admin.torneos.partidos.programar', [$tournament, $match]) }}" class="text-pitch underline">Programación</a>.</p>
        </div>

        {{-- ─── Cuerpo arbitral ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Cuerpo arbitral</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ([
                    'referee'        => 'Árbitro principal',
                    'second_referee' => 'Árbitro secundario',
                ] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute block mb-1">{{ $label }}</label>
                        <input type="text" id="{{ $field }}" name="{{ $field }}" maxlength="120"
                               value="{{ old($field, $match->$field) }}"
                               class="w-full border border-line rounded-md px-3 py-2 text-[14px] focus:outline-none focus:border-pitch">
                    </div>
                @endforeach
            </div>
            <div class="mt-4">
                <label for="referee_notes" class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute block mb-1">Observaciones arbitrales</label>
                <textarea id="referee_notes" name="referee_notes" rows="3" maxlength="1000"
                          placeholder="Incidencias del partido reportadas por el árbitro (expulsiones, lesiones, reclamos, etc.)"
                          class="w-full border border-line rounded-md px-3 py-2 text-[14px] focus:outline-none focus:border-pitch">{{ old('referee_notes', $match->referee_notes) }}</textarea>
            </div>
        </div>

        {{-- ─── W.O. (no presentación) ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5 mb-6">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" x-model="isWalkover" class="w-4 h-4 mt-0.5 rounded border-line accent-alerta">
                <span>
                    <span class="font-display font-bold text-pitch uppercase text-[14px]">Partido ganado por W.O.</span>
                    <span class="block font-mono text-[11px] text-ink-mute mt-0.5">Marca esta opción si un equipo no se presentó. Se asigna ganador y marcador 3–0 sin cargar goles a jugadores.</span>
                </span>
            </label>

            <div x-show="isWalkover" x-cloak class="mt-4 pl-7">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mb-2">¿Qué equipo se presentó (ganador)?</p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <label class="flex items-center gap-2 cursor-pointer border border-line rounded-md px-4 py-2 flex-1"
                           :class="walkoverWinner === 'home' ? 'border-pitch bg-pitch-mist' : ''">
                        <input type="radio" value="home" x-model="walkoverWinner" class="accent-pitch">
                        <span class="font-display font-semibold text-pitch text-[14px]" x-text="homeName"></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer border border-line rounded-md px-4 py-2 flex-1"
                           :class="walkoverWinner === 'away' ? 'border-pitch bg-pitch-mist' : ''">
                        <input type="radio" value="away" x-model="walkoverWinner" class="accent-pitch">
                        <span class="font-display font-semibold text-pitch text-[14px]" x-text="awayName"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ─── Marcador (autocalculado) ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <p class="font-display font-bold text-pitch uppercase text-[15px]">Resultado</p>
                <span class="font-mono text-[11px] text-ink-mute" x-show="!isWalkover">Se calcula con los goles de los jugadores</span>
                <span class="font-mono text-[11px] text-alerta" x-show="isWalkover" x-cloak>Marcador por W.O.</span>
            </div>
            <div class="grid grid-cols-3 items-center gap-4">
                <div class="text-center">
                    <div class="w-5 h-5 rounded-full mx-auto mb-1 border border-line" :style="`background:${homeColor}`"></div>
                    <p class="font-display font-bold text-pitch uppercase text-[14px] break-words" x-text="homeName"></p>
                    <p class="mt-2 text-5xl font-display font-extrabold text-pitch" x-text="displayHome()"></p>
                </div>
                <div class="text-center"><p class="font-display font-bold text-pitch text-4xl">–</p></div>
                <div class="text-center">
                    <div class="w-5 h-5 rounded-full mx-auto mb-1 border border-line" :style="`background:${awayColor}`"></div>
                    <p class="font-display font-bold text-pitch uppercase text-[14px] break-words" x-text="awayName"></p>
                    <p class="mt-2 text-5xl font-display font-extrabold text-pitch" x-text="displayAway()"></p>
                </div>
            </div>

            {{-- Penales: solo en eliminatoria y cuando hay empate --}}
            <div x-show="!isWalkover && isKnockout && homeScore() === awayScore()" x-cloak
                 class="mt-6 border-t border-line-soft pt-4">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mb-3">Definición por penales (empate en eliminatoria)</p>
                <div class="flex items-center justify-center gap-4">
                    <input type="number" min="0" max="99" x-model.number="homePenalties"
                           class="w-16 border border-line rounded px-2 py-1 text-center font-mono focus:outline-none focus:border-pitch">
                    <span class="font-mono text-ink-mute">penales</span>
                    <input type="number" min="0" max="99" x-model.number="awayPenalties"
                           class="w-16 border border-line rounded px-2 py-1 text-center font-mono focus:outline-none focus:border-pitch">
                </div>
            </div>
        </div>

        {{-- ─── Matriz de planilla por equipo ─── --}}
        <template x-for="teamId in [homeTeamId, awayTeamId]" :key="'matrix-'+teamId">
            <div class="bg-white border border-line rounded-md shadow-card-2 mb-6 overflow-hidden"
                 x-show="!isWalkover && playersOf(teamId).length > 0" x-cloak>
                <div class="px-5 py-3 flex items-center justify-between border-b border-line"
                     :style="`border-left: 4px solid ${teamId === homeTeamId ? homeColor : awayColor}`">
                    <p class="font-display font-bold text-pitch uppercase text-[14px]"
                       x-text="(teamId === homeTeamId ? homeName : awayName)"></p>
                    <div class="flex items-center gap-3 font-mono text-[11px] text-ink-mute">
                        <span class="inline-flex items-center gap-1"><x-icon name="ball" class="w-3.5 h-3.5" /> <strong class="text-pitch" x-text="teamGoals(teamId)"></strong></span>
                        <span class="inline-flex items-center gap-1"><x-icon name="card" class="w-3.5 h-3.5 text-warning" /> <strong class="text-pitch" x-text="teamCards(teamId,'yellow')"></strong></span>
                        <span class="inline-flex items-center gap-1"><x-icon name="card" class="w-3.5 h-3.5 text-alerta" /> <strong class="text-pitch" x-text="teamCards(teamId,'red')"></strong></span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-left">
                        <thead>
                            <tr class="bg-pitch-mist font-mono text-[10px] uppercase tracking-wide-label text-ink-mute text-center">
                                <th class="px-4 py-2 text-left">Jugador</th>
                                <th class="px-2 py-2 w-12">Jugó</th>
                                <th class="px-2 py-2 w-12">Tit.</th>
                                <th class="px-2 py-2 w-28"><x-icon name="ball" class="w-3.5 h-3.5 inline" /> Goles</th>
                                <th class="px-2 py-2 w-20"><x-icon name="assist" class="w-3.5 h-3.5 inline" /> Asist.</th>
                                <th class="px-2 py-2 w-16"><x-icon name="card" class="w-3.5 h-3.5 inline text-warning" /></th>
                                <th class="px-2 py-2 w-16"><x-icon name="card" class="w-3.5 h-3.5 inline text-alerta" /></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            <template x-for="p in playersOf(teamId)" :key="'row-'+p.id">
                                <tr :class="p.played ? '' : 'opacity-40'">
                                    {{-- Jugador --}}
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2 min-w-[150px]">
                                            <span class="font-mono text-[11px] text-ink-mute w-5 text-right shrink-0" x-text="p.number ?? '—'"></span>
                                            <div class="min-w-0">
                                                <p class="font-display font-semibold text-pitch text-[13px] leading-tight" x-text="p.name"></p>
                                                <p class="font-mono text-[10px] text-ink-mute leading-tight">
                                                    <span x-show="p.position" x-text="p.position"></span>
                                                    <span x-show="p.is_captain" class="text-gol-deep font-bold">© Capitán</span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Jugó --}}
                                    <td class="px-2 py-2.5 text-center">
                                        <input type="checkbox" x-model="p.played" @change="onPlayedChange(p)"
                                               class="w-4 h-4 rounded border-line accent-pitch cursor-pointer">
                                    </td>
                                    {{-- Titular --}}
                                    <td class="px-2 py-2.5 text-center">
                                        <input type="checkbox" x-model="p.starter" :disabled="!p.played"
                                               class="w-4 h-4 rounded border-line accent-pitch cursor-pointer disabled:cursor-not-allowed">
                                    </td>
                                    {{-- Goles + popup --}}
                                    <td class="px-2 py-2.5">
                                        <div class="flex items-center justify-center gap-1">
                                            <div class="flex items-center border border-line rounded-md overflow-hidden" :class="!p.played ? 'opacity-40 pointer-events-none' : ''">
                                                <button type="button" @click="dec(p,'goals')" :disabled="!p.played || p.goals===0"
                                                        class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">−</button>
                                                <span class="w-7 text-center font-display font-bold text-pitch" x-text="p.goals"></span>
                                                <button type="button" @click="inc(p,'goals')" :disabled="!p.played"
                                                        class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">+</button>
                                            </div>
                                            <button type="button" x-show="p.played && p.goals>0" @click="openPopup(p)"
                                                    :class="p.goalMinutes.some(m => m !== '' && m !== null) ? 'bg-gol/15 text-gol-deep border-gol/40' : 'bg-bone-soft text-ink-mute border-line'"
                                                    class="w-7 h-7 rounded-md border text-[12px] shrink-0" title="Minutos de goles (opcional)">
                                                <span x-text="p.goalMinutes.some(m => m !== '' && m !== null) ? '✓' : '⏱'"></span>
                                            </button>
                                        </div>
                                    </td>
                                    {{-- Asistencias --}}
                                    <td class="px-2 py-2.5">
                                        <div class="flex items-center justify-center border border-line rounded-md overflow-hidden mx-auto w-fit" :class="!p.played ? 'opacity-40 pointer-events-none' : ''">
                                            <button type="button" @click="dec(p,'assists')" :disabled="!p.played || p.assists===0"
                                                    class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">−</button>
                                            <span class="w-7 text-center font-display font-bold text-pitch" x-text="p.assists"></span>
                                            <button type="button" @click="inc(p,'assists')" :disabled="!p.played"
                                                    class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">+</button>
                                        </div>
                                    </td>
                                    {{-- Amarillas --}}
                                    <td class="px-2 py-2.5">
                                        <div class="flex items-center justify-center border border-line rounded-md overflow-hidden mx-auto w-fit" :class="!p.played ? 'opacity-40 pointer-events-none' : ''">
                                            <button type="button" @click="dec(p,'yellow')" :disabled="!p.played || p.yellow===0"
                                                    class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">−</button>
                                            <span class="w-7 text-center font-display font-bold" :class="p.yellow>0 ? 'text-amber-500' : 'text-pitch'" x-text="p.yellow"></span>
                                            <button type="button" @click="inc(p,'yellow',2)" :disabled="!p.played || p.yellow>=2"
                                                    class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">+</button>
                                        </div>
                                    </td>
                                    {{-- Rojas --}}
                                    <td class="px-2 py-2.5">
                                        <div class="flex items-center justify-center border border-line rounded-md overflow-hidden mx-auto w-fit" :class="!p.played ? 'opacity-40 pointer-events-none' : ''">
                                            <button type="button" @click="dec(p,'red')" :disabled="!p.played || p.red===0"
                                                    class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">−</button>
                                            <span class="w-7 text-center font-display font-bold" :class="p.red>0 ? 'text-alerta' : 'text-pitch'" x-text="p.red"></span>
                                            <button type="button" @click="inc(p,'red',1)" :disabled="!p.played || p.red>=1"
                                                    class="w-7 h-7 text-ink-mute hover:bg-bone-soft disabled:opacity-30 font-bold leading-none">+</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        {{-- ─── MVP (figura del partido) — solo si el torneo lo habilita ─── --}}
        @if ($mvpEnabled)
            <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6" x-show="!isWalkover" x-cloak>
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-1 inline-flex items-center gap-1.5"><x-icon name="star" class="w-4 h-4" /> Figura del partido (MVP)</p>
                <p class="font-mono text-[11px] text-ink-mute mb-3">Opcional. Elige un jugador entre los que participaron.</p>
                <select x-model="mvp"
                        class="w-full border border-line rounded-md px-3 py-2 text-[14px] focus:outline-none focus:border-pitch">
                    <option value="">— Sin MVP —</option>
                    <template x-for="p in players.filter(p => p.played)" :key="'mvp-'+p.id">
                        <option :value="String(p.id)" x-text="(p.number ? '#'+p.number+' ' : '') + p.name"></option>
                    </template>
                </select>
            </div>
        @endif

        {{-- ─── Cuerpo técnico y disciplina ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-1">Cuerpo técnico y disciplina</p>
            <p class="font-mono text-[11px] text-ink-mute mb-4">Delegado, faltas acumulativas por tiempo y firma del capitán.</p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ([
                    ['home', $match->homeTeam?->name ?? 'Local',     $hSheet, $match->homeTeam?->color],
                    ['away', $match->awayTeam?->name ?? 'Visitante', $aSheet, $match->awayTeam?->color],
                ] as [$side, $teamName, $sd, $color])
                    <div class="border border-line-soft rounded-md p-4" style="border-left:4px solid {{ $color ?? '#1a1a2e' }}">
                        <p class="font-display font-bold text-pitch uppercase text-[13px] mb-3 truncate">{{ $teamName }}</p>
                        <div class="space-y-3">
                            <div>
                                <label class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute block mb-1">Delegado</label>
                                <input type="text" name="sheet[{{ $side }}][delegate]" maxlength="120"
                                       value="{{ old('sheet.'.$side.'.delegate', $sd['delegate'] ?? '') }}"
                                       class="w-full border border-line rounded-md px-3 py-1.5 text-[13px] focus:outline-none focus:border-pitch">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ([
                                    'fouls_1' => 'Faltas 1ºT',
                                    'fouls_2' => 'Faltas 2ºT',
                                ] as $key => $label)
                                    <div>
                                        <label class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute block mb-1">{{ $label }}</label>
                                        <input type="number" name="sheet[{{ $side }}][{{ $key }}]" min="0" max="99"
                                               value="{{ old('sheet.'.$side.'.'.$key, $sd[$key] ?? '') }}"
                                               class="w-full border border-line rounded-md px-2 py-1.5 text-[13px] font-mono text-center focus:outline-none focus:border-pitch">
                                    </div>
                                @endforeach
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer pt-1">
                                <input type="checkbox" name="sheet[{{ $side }}][captain_signed]" value="1"
                                       @checked(old('sheet.'.$side.'.captain_signed', $sd['captain_signed'] ?? false))
                                       class="w-4 h-4 rounded border-line accent-pitch">
                                <span class="font-mono text-[12px] text-ink">Firma del capitán</span>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Inputs ocultos generados en submit --}}
        <div id="result-hidden"></div>

        {{-- ─── Resumen y envío ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Resumen</p>
            <div class="flex items-center gap-8 mb-3">
                <div class="text-center">
                    <p class="text-[12px] font-mono text-ink-mute uppercase break-words" x-text="homeName"></p>
                    <p class="text-4xl font-display font-extrabold text-pitch" x-text="displayHome()"></p>
                </div>
                <p class="text-pitch font-display font-bold text-3xl">–</p>
                <div class="text-center">
                    <p class="text-[12px] font-mono text-ink-mute uppercase break-words" x-text="awayName"></p>
                    <p class="text-4xl font-display font-extrabold text-pitch" x-text="displayAway()"></p>
                </div>
            </div>
            <p class="text-[13px] text-ink-soft" x-show="!isWalkover">
                Convocados: <span class="font-bold text-pitch" x-text="players.filter(p => p.played).length"></span> ·
                Goles registrados: <span class="font-bold text-pitch" x-text="homeScore() + awayScore()"></span>
            </p>
            <p class="text-[13px] text-alerta" x-show="isWalkover" x-cloak>
                Partido por W.O. — se registrará el ganador sin estadísticas de jugadores.
            </p>
        </div>

        @if ($canEdit)
            <div x-data="{ confirming: false }" class="flex items-center gap-3">
                <template x-if="!confirming">
                    <button type="button" @click="confirming = true"
                            class="px-6 py-3 font-display font-bold uppercase tracking-wide-cta text-[15px] bg-pitch text-bone rounded-md hover:bg-pitch-deep transition-all duration-fast">
                        Guardar planilla
                    </button>
                </template>
                <template x-if="confirming">
                    <div class="flex items-center gap-3 flex-wrap">
                        <p class="text-[13px] text-ink">¿Confirmas? El partido pasará a finalizado y se actualizarán estadísticas y posiciones.</p>
                        <button type="submit"
                                class="px-5 py-2.5 font-display font-bold uppercase text-[14px] bg-gol text-bone rounded-md hover:bg-gol-deep transition-all duration-fast">
                            Sí, confirmar
                        </button>
                        <button type="button" @click="confirming = false"
                                class="px-4 py-2 font-display font-bold uppercase text-[13px] text-pitch border border-pitch rounded-md hover:bg-pitch hover:text-bone transition-all duration-fast">
                            Revisar
                        </button>
                    </div>
                </template>
                <a href="{{ route('admin.torneos.partidos.index', $tournament) }}"
                   class="px-4 py-3 font-display font-semibold uppercase text-[13px] text-pitch hover:underline">
                    Cancelar
                </a>
            </div>
        @else
            <div class="flex items-center gap-3">
                <x-btn :href="route('admin.torneos.partidos.pdf', [$tournament, $match])" variant="primary"><x-icon name="download" class="w-4 h-4 inline -mt-0.5" /> Descargar planilla (PDF)</x-btn>
                <a href="{{ route('admin.torneos.partidos.index', $tournament) }}"
                   class="px-4 py-3 font-display font-semibold uppercase text-[13px] text-pitch hover:underline">
                    Volver a partidos
                </a>
            </div>
        @endif
    </form>
</div>

<script>
function resultadoForm(cfg) {
    return {
        homeName:       cfg.homeName,
        awayName:       cfg.awayName,
        homeColor:      cfg.homeColor,
        awayColor:      cfg.awayColor,
        homeTeamId:     cfg.homeTeamId,
        awayTeamId:     cfg.awayTeamId,
        players:        cfg.players,
        isWalkover:     cfg.isWalkover,
        walkoverWinner: cfg.walkoverWinner,
        walkoverWin:    cfg.walkoverWin,
        isKnockout:     cfg.isKnockout,
        homePenalties:  cfg.homePenalties,
        awayPenalties:  cfg.awayPenalties,
        mvpEnabled:     cfg.mvpEnabled,
        mvp:            cfg.mvp,

        popup: { open: false, playerId: null, playerName: '', minutes: [] },

        playersOf(teamId) {
            return this.players.filter(p => p.team_id === teamId);
        },

        // ── Marcador autocalculado desde los goles de los jugadores ──
        homeScore() { return this.teamGoals(this.homeTeamId); },
        awayScore() { return this.teamGoals(this.awayTeamId); },
        teamGoals(teamId) {
            return this.playersOf(teamId).reduce((s, p) => s + (p.played ? p.goals : 0), 0);
        },
        teamCards(teamId, type) {
            return this.playersOf(teamId).reduce((s, p) => s + (p.played ? p[type] : 0), 0);
        },
        displayHome() {
            if (this.isWalkover) return this.walkoverWinner === 'home' ? this.walkoverWin : (this.walkoverWinner === 'away' ? 0 : '—');
            return this.homeScore();
        },
        displayAway() {
            if (this.isWalkover) return this.walkoverWinner === 'away' ? this.walkoverWin : (this.walkoverWinner === 'home' ? 0 : '—');
            return this.awayScore();
        },

        // ── Contadores ──
        inc(p, field, max = 99) {
            if (!p.played) return;
            if (p[field] < max) {
                p[field]++;
                if (field === 'goals') p.goalMinutes.push('');
            }
        },
        dec(p, field) {
            if (p[field] > 0) {
                p[field]--;
                if (field === 'goals') p.goalMinutes.pop();
            }
        },
        onPlayedChange(p) {
            if (!p.played) {
                p.starter = false;
                p.goals = 0; p.assists = 0; p.yellow = 0; p.red = 0;
                p.goalMinutes = [];
            } else {
                p.starter = true;
            }
        },

        // ── Popup minutos ──
        openPopup(p) {
            while (p.goalMinutes.length < p.goals) p.goalMinutes.push('');
            p.goalMinutes.length = p.goals;
            this.popup = { open: true, playerId: p.id, playerName: p.name, minutes: [...p.goalMinutes] };
        },
        saveMinutes() {
            const p = this.players.find(x => x.id === this.popup.playerId);
            if (p) p.goalMinutes = [...this.popup.minutes];
            this.closePopup();
        },
        closePopup() { this.popup.open = false; },

        // ── Envío ──
        submit(form) {
            const c = document.getElementById('result-hidden');
            c.innerHTML = '';
            const add = (name, value) => {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = name; i.value = value;
                c.appendChild(i);
            };

            add('is_walkover', this.isWalkover ? 1 : 0);

            if (this.isWalkover) {
                add('walkover_winner', this.walkoverWinner || '');
                // El marcador real lo asigna el backend (convencional). Enviamos un
                // placeholder para cumplir la validación required.
                const hs = this.walkoverWinner === 'home' ? this.walkoverWin : 0;
                const as = this.walkoverWinner === 'away' ? this.walkoverWin : 0;
                add('home_score', hs);
                add('away_score', as);
                form.submit();
                return;
            }

            add('home_score', this.homeScore());
            add('away_score', this.awayScore());

            // MVP (solo si el torneo lo habilita y se eligió un jugador que jugó)
            if (this.mvpEnabled && this.mvp) {
                const mvpId = parseInt(this.mvp, 10);
                if (this.players.some(p => p.id === mvpId && p.played)) {
                    add('mvp_team_player_id', mvpId);
                }
            }

            if (this.isKnockout && this.homeScore() === this.awayScore()) {
                if (this.homePenalties !== '' && this.homePenalties !== null) add('home_penalties', this.homePenalties);
                if (this.awayPenalties !== '' && this.awayPenalties !== null) add('away_penalties', this.awayPenalties);
            }

            // Lineups (jugadores que participaron)
            let li = 0;
            this.players.filter(p => p.played).forEach(p => {
                add(`lineups[${li}][team_player_id]`, p.id);
                add(`lineups[${li}][team_id]`, p.team_id);
                add(`lineups[${li}][started]`, p.starter ? 1 : 0);
                add(`lineups[${li}][minute_in]`, 0);
                add(`lineups[${li}][minute_out]`, '');
                li++;
            });

            // Eventos: se expanden los contadores en filas individuales
            let ei = 0;
            this.players.filter(p => p.played).forEach(p => {
                for (let g = 0; g < p.goals; g++) {
                    add(`events[${ei}][team_player_id]`, p.id);
                    add(`events[${ei}][type]`, 'goal');
                    add(`events[${ei}][minute]`, (p.goalMinutes[g] ?? '') === null ? '' : (p.goalMinutes[g] ?? ''));
                    ei++;
                }
                for (let a = 0; a < p.assists; a++) {
                    add(`events[${ei}][team_player_id]`, p.id);
                    add(`events[${ei}][type]`, 'assist');
                    add(`events[${ei}][minute]`, '');
                    ei++;
                }
                for (let y = 0; y < p.yellow; y++) {
                    add(`events[${ei}][team_player_id]`, p.id);
                    add(`events[${ei}][type]`, 'yellow_card');
                    add(`events[${ei}][minute]`, '');
                    ei++;
                }
                for (let r = 0; r < p.red; r++) {
                    add(`events[${ei}][team_player_id]`, p.id);
                    add(`events[${ei}][type]`, 'red_card');
                    add(`events[${ei}][minute]`, '');
                    ei++;
                }
            });

            form.submit();
        },
    };
}
</script>
@endsection
