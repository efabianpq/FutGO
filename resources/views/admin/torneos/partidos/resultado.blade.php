@extends('layouts.app')
@section('title', 'Admin · Resultado · Partido #' . $match->match_number)

@section('content')
@include('admin.torneos._nav')

@php
// Primer tipo de evento habilitado en stats_config (fix Prompt 8)
$eventTypeDefault = 'goal';
if (($statsConfig['goals'] ?? true) === false) {
    if ($statsConfig['assists'] ?? true)       $eventTypeDefault = 'assist';
    elseif ($statsConfig['yellow_cards'] ?? true) $eventTypeDefault = 'yellow_card';
    elseif ($statsConfig['red_cards'] ?? true)    $eventTypeDefault = 'red_card';
    else                                           $eventTypeDefault = 'substitution_in';
}

// Construir datos para Alpine.
// Autogeneración: si la planilla nunca se guardó (sin lineups), se pre-carga la
// convocatoria completa con todo el roster activo como titular. El admin solo
// destilda a los ausentes. En re-edición se respeta lo guardado.
$freshSheet = $existingLineups->isEmpty();

$buildPlayers = function($players, $teamId, $teamName, $teamColor, $captainId) use ($existingLineups, $freshSheet) {
    return $players->map(fn($p) => [
        'id'          => $p->id,
        'name'        => $p->user?->name ?? 'Jugador',
        'number'      => $p->jersey_number,
        'position'    => $p->position,
        'is_captain'  => $captainId && $p->user_id === $captainId,
        'team_id'     => $teamId,
        'team_name'   => $teamName,
        'team_color'  => $teamColor,
        'in_lineup'   => $freshSheet ? true : $existingLineups->has($p->id),
        'started'     => $existingLineups->has($p->id) ? (bool)$existingLineups[$p->id]->started : true,
        'minute_out'  => $existingLineups->has($p->id) ? $existingLineups[$p->id]->minute_out : null,
        'minute_in'   => $existingLineups->has($p->id) ? $existingLineups[$p->id]->minute_in : 0,
    ])->values()->all();
};

$allPlayersData = array_merge(
    $buildPlayers($homePlayers, $match->home_team_id, $match->homeTeam?->name ?? 'Local', $match->homeTeam?->color ?? '#1a1a2e', $match->homeTeam?->captain_user_id),
    $buildPlayers($awayPlayers, $match->away_team_id, $match->awayTeam?->name ?? 'Visitante', $match->awayTeam?->color ?? '#4a4a6a', $match->awayTeam?->captain_user_id),
);

// Datos del acta (planilla oficial) para pre-llenar en re-edición.
$ms        = $match->match_sheet ?? [];
$hSheet    = $ms['home'] ?? [];
$aSheet    = $ms['away'] ?? [];
$homeName  = $match->homeTeam?->name ?? 'Local';
$awayName  = $match->awayTeam?->name ?? 'Visitante';

// Categoría legible.
$categoryLabels = [
    'libre' => 'Libre', 'veteranos' => 'Veteranos', 'sub15' => 'Sub-15', 'sub17' => 'Sub-17',
    'sub20' => 'Sub-20', 'femenino' => 'Femenino', 'mixto' => 'Mixto',
];

$formInit = [
    'homeScore'       => old('home_score', $match->home_score ?? ''),
    'awayScore'       => old('away_score', $match->away_score ?? ''),
    'homeName'        => $match->homeTeam?->name ?? 'Local',
    'awayName'        => $match->awayTeam?->name ?? 'Visitante',
    'homeColor'       => $match->homeTeam?->color ?? '#1a1a2e',
    'awayColor'       => $match->awayTeam?->color ?? '#4a4a6a',
    'homeTeamId'      => $match->home_team_id,
    'awayTeamId'      => $match->away_team_id,
    'players'         => $allPlayersData,
    'statsConfig'     => $statsConfig,
    'eventTypeDefault'=> $eventTypeDefault,
    'existingEvents'  => $match->events->map(fn($e) => [
        'team_player_id' => $e->team_player_id,
        'type'           => $e->type,
        'minute'         => $e->minute,
    ])->values()->all(),
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
                <h1 class="font-display font-bold text-display-m text-pitch uppercase">Planilla del Partido</h1>
                <x-badge :variant="$mStatusVariant">{{ $mStatusLabel }}</x-badge>
            </div>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                {{ $match->phase->name }} · Partido #{{ $match->match_number }}
            </p>
        </div>
        <x-btn :href="route('admin.torneos.partidos.pdf', [$tournament, $match])" variant="ghost" size="sm">⬇ Descargar PDF</x-btn>
    </div>

    @unless ($canEdit)
        <div class="mb-6 bg-pitch-mist border border-line rounded-md px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">
                Este partido está <strong class="text-pitch">finalizado</strong>. La planilla quedó como documento oficial.
                Para corregir datos, anulá el resultado y volvé a cargarlo.
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

        {{-- ─── Datos del partido (contexto de la planilla) ─── --}}
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

        {{-- ─── Cuerpo arbitral y mesa ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Cuerpo arbitral y mesa</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ([
                    'referee'        => 'Árbitro',
                    'second_referee' => 'Segundo árbitro',
                    'third_referee'  => 'Tercer árbitro',
                    'timekeeper'     => 'Cronometrador',
                    'coordinator'    => 'Coordinador',
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

        {{-- ─── Sección 1: Marcador ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Resultado final</p>
            <div class="grid grid-cols-3 items-center gap-4">
                <div class="text-center">
                    <div class="w-5 h-5 rounded-full mx-auto mb-1 border border-line"
                         :style="`background:${homeColor}`"></div>
                    <p class="font-display font-bold text-pitch uppercase text-[14px]" x-text="homeName"></p>
                    <input type="number" name="home_score" min="0" max="99" required
                           x-model.number="homeScore"
                           class="mt-3 w-24 mx-auto block text-center text-4xl font-display font-extrabold text-pitch border-2 border-pitch rounded-lg py-2 focus:outline-none focus:border-gol">
                </div>
                <div class="text-center">
                    <p class="font-display font-bold text-pitch text-4xl">–</p>
                </div>
                <div class="text-center">
                    <div class="w-5 h-5 rounded-full mx-auto mb-1 border border-line"
                         :style="`background:${awayColor}`"></div>
                    <p class="font-display font-bold text-pitch uppercase text-[14px]" x-text="awayName"></p>
                    <input type="number" name="away_score" min="0" max="99" required
                           x-model.number="awayScore"
                           class="mt-3 w-24 mx-auto block text-center text-4xl font-display font-extrabold text-pitch border-2 border-pitch rounded-lg py-2 focus:outline-none focus:border-gol">
                </div>
            </div>

            {{-- Marcador por periodos (informativo) --}}
            <div class="mt-6 border-t border-line-soft pt-4">
                <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mb-3">Marcador por periodos</p>
                <div class="overflow-x-auto">
                    <table class="w-full max-w-md text-[13px]">
                        <thead>
                            <tr class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">
                                <th class="text-left py-1">Periodo</th>
                                <th class="py-1 px-2 text-center truncate">{{ $homeName }}</th>
                                <th class="py-1 px-2 text-center truncate">{{ $awayName }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @foreach ([
                                ['1er tiempo', 'home_score_ht', 'away_score_ht'],
                                ['Prórroga',   'home_score_et', 'away_score_et'],
                                ['Penales',    'home_penalties', 'away_penalties'],
                            ] as [$label, $hField, $aField])
                                <tr>
                                    <td class="py-1.5 font-display font-semibold text-pitch">{{ $label }}</td>
                                    <td class="py-1.5 px-2 text-center">
                                        <input type="number" name="{{ $hField }}" min="0" max="99"
                                               value="{{ old($hField, $match->$hField) }}"
                                               class="w-16 border border-line rounded px-2 py-1 text-center font-mono focus:outline-none focus:border-pitch">
                                    </td>
                                    <td class="py-1.5 px-2 text-center">
                                        <input type="number" name="{{ $aField }}" min="0" max="99"
                                               value="{{ old($aField, $match->$aField) }}"
                                               class="w-16 border border-line rounded px-2 py-1 text-center font-mono focus:outline-none focus:border-pitch">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ─── Sección 2: Convocatoria (Lineup) ─── --}}
        <template x-for="teamId in [homeTeamId, awayTeamId]" :key="'lineup-'+teamId">
            <div class="bg-white border border-line rounded-md shadow-card-2 mb-6 overflow-hidden"
                 x-show="playersOf(teamId).length > 0">
                <div class="px-6 py-4 border-b border-line"
                     :style="`border-left: 4px solid ${teamId === homeTeamId ? homeColor : awayColor}`">
                    <p class="font-display font-bold text-pitch uppercase text-[15px]"
                       x-text="(teamId === homeTeamId ? homeName : awayName) + ' — Convocatoria'"></p>
                    <p class="font-mono text-[11px] text-ink-mute mt-0.5">Marcá los jugadores que participaron en el partido</p>
                </div>
                <div class="p-4 space-y-2">
                    <template x-for="player in playersOf(teamId)" :key="'lu-'+player.id">
                        <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line-soft last:border-0">
                            {{-- Nombre + número --}}
                            <div class="flex items-center gap-2 w-44">
                                <span class="font-mono text-[11px] text-ink-mute w-5 text-right" x-text="player.number ?? '—'"></span>
                                <span class="font-display font-semibold text-pitch text-[13px]" x-text="player.name"></span>
                                <span x-show="player.is_captain" class="font-mono text-[9px] text-gol-deep uppercase font-bold">©</span>
                            </div>

                            {{-- Checkbox participó --}}
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox"
                                       :checked="player.in_lineup"
                                       @change="toggleLineup(player.id, $event.target.checked)"
                                       class="w-4 h-4 rounded border-line accent-pitch">
                                <span class="font-mono text-[12px] text-ink">Jugó</span>
                            </label>

                            <template x-if="player.in_lineup">
                                <div class="flex items-center gap-3">
                                    {{-- Titular --}}
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox"
                                               :checked="player.started"
                                               @change="player.started = $event.target.checked"
                                               class="w-4 h-4 rounded border-line accent-pitch">
                                        <span class="font-mono text-[12px] text-ink-mute">Titular</span>
                                    </label>

                                    {{-- Minuto de salida (opcional) --}}
                                    <div class="flex items-center gap-1">
                                        <span class="font-mono text-[11px] text-ink-mute">Sale min.</span>
                                        <input type="number"
                                               :value="player.minute_out"
                                               @input="player.minute_out = $event.target.value ? parseInt($event.target.value) : null"
                                               min="1" max="120" placeholder="—"
                                               class="w-14 border border-line rounded px-2 py-1 text-[12px] font-mono text-center focus:outline-none focus:border-pitch">
                                    </div>

                                    {{-- Minuto de entrada (solo si no es titular) --}}
                                    <template x-if="!player.started">
                                        <div class="flex items-center gap-1">
                                            <span class="font-mono text-[11px] text-ink-mute">Entra min.</span>
                                            <input type="number"
                                                   :value="player.minute_in || ''"
                                                   @input="player.minute_in = $event.target.value ? parseInt($event.target.value) : 0"
                                                   min="1" max="120"
                                                   class="w-14 border border-line rounded px-2 py-1 text-[12px] font-mono text-center focus:outline-none focus:border-pitch">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- ─── Sección 3: Eventos ─── --}}
        <template x-for="teamId in [homeTeamId, awayTeamId]" :key="'events-'+teamId">
            <div class="bg-white border border-line rounded-md shadow-card-2 mb-6 overflow-hidden"
                 x-show="lineupOf(teamId).length > 0">
                <div class="px-6 py-4 border-b border-line"
                     :style="`border-left: 4px solid ${teamId === homeTeamId ? homeColor : awayColor}`">
                    <p class="font-display font-bold text-pitch uppercase text-[15px]"
                       x-text="(teamId === homeTeamId ? homeName : awayName) + ' — Eventos'"></p>
                    <p class="font-mono text-[11px] text-ink-mute mt-0.5">Solo jugadores en la convocatoria</p>
                </div>
                <div class="p-4 space-y-3">
                    <template x-for="player in lineupOf(teamId)" :key="'ev-player-'+player.id">
                        <div class="border border-line-soft rounded-md p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[11px] text-ink-mute w-6 text-right" x-text="player.number ?? '—'"></span>
                                    <span class="font-display font-semibold text-pitch text-[14px]" x-text="player.name"></span>
                                    <span x-show="player.position" class="font-mono text-[10px] text-ink-mute uppercase" x-text="player.position"></span>
                                </div>
                                <button type="button" @click="addEvent(player.id)"
                                        class="text-[12px] font-mono text-pitch hover:text-gol-deep border border-line rounded px-2 py-1 transition-colors duration-fast">
                                    + Evento
                                </button>
                            </div>

                            <template x-for="(ev, idx) in eventsOf(player.id)" :key="ev._key">
                                <div class="flex items-center gap-2 mt-2">
                                    <select x-model="ev.type"
                                            class="border border-line rounded px-2 py-1 text-[12px] font-mono focus:outline-none focus:border-pitch flex-1">
                                        <template x-if="statsConfig.goals"><option value="goal">⚽ Gol</option></template>
                                        <template x-if="statsConfig.goals"><option value="own_goal">🔴 Gol en contra</option></template>
                                        <template x-if="statsConfig.assists"><option value="assist">👟 Asistencia</option></template>
                                        <template x-if="statsConfig.yellow_cards"><option value="yellow_card">🟨 Amarilla</option></template>
                                        <template x-if="statsConfig.red_cards"><option value="red_card">🟥 Roja</option></template>
                                        <option value="substitution_in">↗ Entra</option>
                                        <option value="substitution_out">↙ Sale</option>
                                    </select>
                                    <input type="number" x-model.number="ev.minute"
                                           min="1" max="120" placeholder="Min"
                                           class="border border-line rounded px-2 py-1 text-[12px] font-mono w-16 text-center focus:outline-none focus:border-pitch">
                                    <button type="button" @click="removeEvent(ev._key)"
                                            class="text-alerta font-mono text-[14px] hover:text-alerta-deep w-6 text-center leading-none">✕</button>
                                </div>
                            </template>

                            <p x-show="eventsOf(player.id).length === 0"
                               class="text-[11px] font-mono text-ink-mute mt-1">Sin eventos</p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- ─── Cuerpo técnico y disciplina por equipo ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-1">Cuerpo técnico y disciplina</p>
            <p class="font-mono text-[11px] text-ink-mute mb-4">Faltas acumulativas por tiempo y tiempos muertos (fútbol sala).</p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ([
                    ['home', $homeName, $hSheet, $match->homeTeam?->color],
                    ['away', $awayName, $aSheet, $match->awayTeam?->color],
                ] as [$side, $teamName, $sd, $color])
                    <div class="border border-line-soft rounded-md p-4" style="border-left:4px solid {{ $color ?? '#1a1a2e' }}">
                        <p class="font-display font-bold text-pitch uppercase text-[13px] mb-3 truncate">{{ $teamName }}</p>
                        <div class="space-y-3">
                            @foreach ([
                                'coach'     => 'D.T. (Director Técnico)',
                                'assistant' => 'A. Técnico',
                                'delegate'  => 'Delegado',
                            ] as $key => $label)
                                <div>
                                    <label class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute block mb-1">{{ $label }}</label>
                                    <input type="text" name="sheet[{{ $side }}][{{ $key }}]" maxlength="120"
                                           value="{{ old('sheet.'.$side.'.'.$key, $sd[$key] ?? '') }}"
                                           class="w-full border border-line rounded-md px-3 py-1.5 text-[13px] focus:outline-none focus:border-pitch">
                                </div>
                            @endforeach

                            <div class="grid grid-cols-3 gap-2">
                                @foreach ([
                                    'fouls_1'  => 'Faltas 1ºT',
                                    'fouls_2'  => 'Faltas 2ºT',
                                    'timeouts' => 'T. muertos',
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
        <div id="events-hidden"></div>

        {{-- ─── Sección 4: Resumen y envío ─── --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Resumen</p>
            <div class="flex items-center gap-8 mb-3">
                <div class="text-center">
                    <p class="text-[12px] font-mono text-ink-mute uppercase" x-text="homeName"></p>
                    <p class="text-4xl font-display font-extrabold text-pitch" x-text="homeScore !== '' ? homeScore : '—'"></p>
                </div>
                <p class="text-pitch font-display font-bold text-3xl">–</p>
                <div class="text-center">
                    <p class="text-[12px] font-mono text-ink-mute uppercase" x-text="awayName"></p>
                    <p class="text-4xl font-display font-extrabold text-pitch" x-text="awayScore !== '' ? awayScore : '—'"></p>
                </div>
            </div>
            <p class="text-[13px] text-ink-soft">
                Convocados: <span class="font-bold text-pitch" x-text="players.filter(p => p.in_lineup).length"></span> ·
                Eventos: <span class="font-bold text-pitch" x-text="events.length"></span>
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
                    <div class="flex items-center gap-3">
                        <p class="text-[13px] text-ink">¿Confirmás? El partido pasará a finalizado y se actualizarán estadísticas y posiciones.</p>
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
                <x-btn :href="route('admin.torneos.partidos.pdf', [$tournament, $match])" variant="primary">⬇ Descargar planilla (PDF)</x-btn>
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
        homeScore:        cfg.homeScore,
        awayScore:        cfg.awayScore,
        homeName:         cfg.homeName,
        awayName:         cfg.awayName,
        homeColor:        cfg.homeColor,
        awayColor:        cfg.awayColor,
        homeTeamId:       cfg.homeTeamId,
        awayTeamId:       cfg.awayTeamId,
        players:          cfg.players,   // array completo con in_lineup/started/minute_out
        statsConfig:      cfg.statsConfig,
        eventTypeDefault: cfg.eventTypeDefault,
        _nextKey:         0,
        events: cfg.existingEvents.map((e, i) => ({
            _key:           i,
            team_player_id: e.team_player_id,
            type:           e.type,
            minute:         e.minute,
        })),

        init() {
            this._nextKey = this.events.length;
        },

        playersOf(teamId) {
            return this.players.filter(p => p.team_id === teamId);
        },

        /** Jugadores en lineup de un equipo (para la sección de eventos) */
        lineupOf(teamId) {
            return this.players.filter(p => p.team_id === teamId && p.in_lineup);
        },

        toggleLineup(playerId, checked) {
            const p = this.players.find(p => p.id === playerId);
            if (!p) return;
            p.in_lineup = checked;
            if (!checked) {
                // Quitar eventos del jugador al sacar del lineup
                this.events = this.events.filter(e => e.team_player_id !== playerId);
            }
        },

        eventsOf(playerId) {
            return this.events.filter(e => e.team_player_id === playerId);
        },

        addEvent(playerId) {
            this.events.push({
                _key:           this._nextKey++,
                team_player_id: playerId,
                type:           this.eventTypeDefault,
                minute:         1,
            });
        },

        removeEvent(key) {
            this.events = this.events.filter(e => e._key !== key);
        },

        submit(form) {
            const container = document.getElementById('events-hidden');
            container.innerHTML = '';

            // Lineups
            let li = 0;
            this.players.filter(p => p.in_lineup).forEach(player => {
                const fields = {
                    [`lineups[${li}][team_player_id]`]: player.id,
                    [`lineups[${li}][team_id]`]:        player.team_id,
                    [`lineups[${li}][started]`]:        player.started ? 1 : 0,
                    [`lineups[${li}][minute_in]`]:      player.minute_in ?? 0,
                    [`lineups[${li}][minute_out]`]:     player.minute_out ?? '',
                };
                Object.entries(fields).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = name;
                    input.value = value;
                    container.appendChild(input);
                });
                li++;
            });

            // Events
            this.events.forEach((ev, idx) => {
                const fields = {
                    [`events[${idx}][team_player_id]`]: ev.team_player_id,
                    [`events[${idx}][type]`]:           ev.type,
                    [`events[${idx}][minute]`]:         ev.minute,
                };
                Object.entries(fields).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = name;
                    input.value = value;
                    container.appendChild(input);
                });
            });

            form.submit();
        },
    };
}
</script>
@endsection
