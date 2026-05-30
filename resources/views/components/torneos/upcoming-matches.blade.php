{{--
    Componente: próximos partidos de un torneo o de un equipo específico.

    Props:
      $matches  — colección de TournamentMatch. Para evitar N+1, el caller DEBE
                  cargar las relaciones que quiera mostrar: homeTeam, awayTeam,
                  phase, group. El componente nunca dispara lazy-loading: solo
                  lee relaciones ya cargadas (relationLoaded).
      $limit    — cuántos mostrar (default 5)
      $teamId   — si se pasa, muestra "vs Rival" en lugar de "Local vs Visitante"
      $showPhase— mostrar nombre de fase (default true)

    Endurecimiento:
      - Equipos nulos o sin cargar se muestran como "Por definir".
      - No se generan enlaces hacia equipos inexistentes.
--}}
@props([
    'matches'   => collect(),
    'limit'     => 5,
    'teamId'    => null,
    'showPhase' => true,
])

@php
    $shown = $matches->take($limit);

    // Devuelve el modelo de equipo SOLO si la relación está cargada (sin N+1); si no, null.
    $teamModel = function ($match, string $relation) {
        return $match->relationLoaded($relation) ? $match->{$relation} : null;
    };

    // Nombre seguro de un equipo dado relación + id. "Por definir" si no hay equipo.
    $teamName = function ($team, $teamId) {
        return ($team && $team->name) ? $team->name : 'Por definir';
    };
@endphp

@if ($shown->isEmpty())
    <p class="text-[13px] text-ink-mute italic">Sin próximos partidos programados.</p>
@else
    <ul class="divide-y divide-line-soft">
        @foreach ($shown as $match)
            @php
                $home = $teamModel($match, 'homeTeam');
                $away = $teamModel($match, 'awayTeam');

                $isTeamHome = $teamId && $match->home_team_id == $teamId;
                $rival      = $teamId ? ($isTeamHome ? $away : $home) : null;
                $rivalId    = $teamId ? ($isTeamHome ? $match->away_team_id : $match->home_team_id) : null;

                $phase = $teamModel($match, 'phase');
                $group = $teamModel($match, 'group');
            @endphp
            <li class="py-3 flex items-center gap-3">
                {{-- Fecha / hora --}}
                <div class="w-14 shrink-0 text-center">
                    @if ($match->scheduled_at)
                        <p class="font-mono text-[11px] text-ink-mute leading-tight">
                            {{ $match->scheduled_at->format('d/m') }}
                        </p>
                        <p class="font-mono text-[12px] text-pitch font-semibold leading-tight">
                            {{ $match->scheduled_at->format('H:i') }}
                        </p>
                    @else
                        <p class="font-mono text-[11px] text-ink-mute">—</p>
                    @endif
                </div>

                {{-- Partido --}}
                <div class="flex-1 min-w-0">
                    @if ($teamId)
                        <p class="font-display font-semibold text-pitch text-[13px] leading-tight truncate">
                            vs {{ $teamName($rival, $rivalId) }}
                        </p>
                        <p class="font-mono text-[11px] text-ink-mute leading-tight">
                            {{ $isTeamHome ? 'Local' : 'Visitante' }}
                        </p>
                    @else
                        <p class="font-display font-semibold text-pitch text-[13px] leading-tight truncate">
                            {{ $teamName($home, $match->home_team_id) }}
                            <span class="font-mono text-[11px] text-ink-mute mx-1">vs</span>
                            {{ $teamName($away, $match->away_team_id) }}
                        </p>
                    @endif
                    @if ($showPhase && $phase)
                        <p class="font-mono text-[10px] text-ink-mute uppercase tracking-wide-label leading-tight">
                            {{ $phase->name }}
                            @if ($group)
                                · Grupo {{ $group->name }}
                            @endif
                        </p>
                    @endif
                </div>

                {{-- Estado --}}
                <div class="shrink-0">
                    @if ($match->isLive())
                        <x-badge variant="live">En vivo</x-badge>
                    @else
                        <span class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Prog.</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
