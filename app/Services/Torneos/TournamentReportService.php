<?php

namespace App\Services\Torneos;

use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Fuente única de datos de lectura del torneo (Sesión E).
 *
 * La usan el portal público, las tarjetas compartibles (SVG) y la exportación
 * PDF/CSV. Todas las consultas usan eager loading para EVITAR N+1: el número de
 * consultas es constante sin importar la cantidad de equipos/partidos/jugadores.
 *
 * PRIVACIDAD: del usuario solo se cargan id y name (nunca email/teléfono/documento).
 */
class TournamentReportService
{
    /** Fases de grupos con sus grupos y posiciones (equipo incluido), ordenadas. */
    public function groupStandings(Tournament $tournament): Collection
    {
        return $tournament->phases()
            ->where('type', 'groups')
            ->with([
                'groups' => fn ($q) => $q->orderBy('order'),
                'groups.standings' => fn ($q) => $q->orderBy('position'),
                'groups.standings.team:id,name,color,club_id',
                'groups.standings.team.club:id,slug,name',
            ])
            ->orderBy('order')
            ->get();
    }

    /** Partidos finalizados (más recientes primero). */
    public function finishedMatches(Tournament $tournament, ?int $limit = null): Collection
    {
        $query = $this->matchesQuery($tournament)
            ->where('status', 'finished')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('match_number');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /** Próximos partidos programados (fecha asc; los sin fecha al final). */
    public function upcomingMatches(Tournament $tournament, ?int $limit = null): Collection
    {
        $query = $this->matchesQuery($tournament)
            ->where('status', 'scheduled')
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /** Tabla de goleadores (goles > 0), con jugador y equipo. */
    public function topScorers(Tournament $tournament, int $limit = 10): Collection
    {
        return PlayerStat::where('tournament_id', $tournament->id)
            ->where('goals', '>', 0)
            ->with($this->playerStatRelations())
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderByDesc('matches_played')
            ->limit($limit)
            ->get();
    }

    /**
     * Variantes paginadas de finishedMatches/upcomingMatches/topScorers, solo
     * para el portal público (deuda #9). Los métodos originales quedan intactos
     * para tarjetas compartibles y exportación PDF/CSV (necesitan un top-N fijo,
     * no paginado). Cada listado usa un pageName distinto porque los 3 conviven
     * en la misma página.
     */
    public function paginatedFinishedMatches(Tournament $tournament, int $perPage = 12): LengthAwarePaginator
    {
        return $this->matchesQuery($tournament)
            ->where('status', 'finished')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('match_number')
            ->paginate($perPage, ['*'], 'resultados_page');
    }

    public function paginatedUpcomingMatches(Tournament $tournament, int $perPage = 12): LengthAwarePaginator
    {
        return $this->matchesQuery($tournament)
            ->where('status', 'scheduled')
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->paginate($perPage, ['*'], 'proximos_page');
    }

    public function paginatedTopScorers(Tournament $tournament, int $perPage = 10): LengthAwarePaginator
    {
        return PlayerStat::where('tournament_id', $tournament->id)
            ->where('goals', '>', 0)
            ->with($this->playerStatRelations())
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderByDesc('matches_played')
            ->paginate($perPage, ['*'], 'goleadores_page');
    }

    /** Estadísticas completas de jugadores con al menos un partido jugado. */
    public function playerStats(Tournament $tournament): Collection
    {
        return PlayerStat::where('tournament_id', $tournament->id)
            ->where('matches_played', '>', 0)
            ->with($this->playerStatRelations())
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderByDesc('matches_played')
            ->get();
    }

    /** Líderes de MVP (mvps > 0). */
    public function mvpLeaders(Tournament $tournament, int $limit = 5): Collection
    {
        return PlayerStat::where('tournament_id', $tournament->id)
            ->where('mvps', '>', 0)
            ->with($this->playerStatRelations())
            ->orderByDesc('mvps')
            ->limit($limit)
            ->get();
    }

    /** Último partido finalizado que tenga figura del partido (MVP) asignada. */
    public function latestMvpMatch(Tournament $tournament): ?TournamentMatch
    {
        if (! $tournament->mvpEnabled()) {
            return null;
        }

        return $this->matchesQuery($tournament)
            ->where('status', 'finished')
            ->whereNotNull('mvp_team_player_id')
            ->with(['mvp.user:id,name', 'mvp.team:id,name,color'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('match_number')
            ->first();
    }

    /** Resumen rápido para la cabecera del portal. */
    public function summary(Tournament $tournament): array
    {
        $phaseIds = $tournament->phases()->pluck('id');

        return [
            'teams'     => $tournament->teams()->where('status', 'approved')->count(),
            'played'    => TournamentMatch::whereIn('phase_id', $phaseIds)->where('status', 'finished')->count(),
            'scheduled' => TournamentMatch::whereIn('phase_id', $phaseIds)->where('status', 'scheduled')->count(),
        ];
    }

    /** Base de consulta de partidos del torneo con relaciones eager. */
    private function matchesQuery(Tournament $tournament)
    {
        return TournamentMatch::whereHas('phase', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->with([
                'homeTeam:id,name,color',
                'awayTeam:id,name,color',
                'phase:id,name,type',
                'group:id,name',
            ]);
    }

    /** Relaciones para PlayerStat cargando del usuario SOLO id y name (privacidad). */
    private function playerStatRelations(): array
    {
        return [
            'teamPlayer:id,user_id,team_id,full_name,verification_status',
            'teamPlayer.user:id,name',
            'teamPlayer.team:id,name,color',
        ];
    }
}
