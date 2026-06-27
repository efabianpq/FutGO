<?php

namespace App\Services\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FutGO Social — Fase 2 · Sesión S2-A · "Jugué con vos".
 *
 * Deriva, SIN tabla propia, los jugadores con quienes un usuario compartió cancha.
 * Dos fuentes, ambas con agregación en una sola query (no hay N+1):
 *
 *  1. Partidos de torneo: `match_lineups` es la fuente de verdad de quién jugó.
 *     Dos usuarios "compartieron cancha" si aparecen en la alineación del MISMO
 *     `tournament_match` (en cualquiera de los dos equipos — rivales o compañeros).
 *
 *  2. Amistosos: `friendly_matches` no tiene alineación; los participantes se
 *     derivan de la plantilla actual (`club_players`) de los dos clubs. Dos
 *     usuarios comparten un amistoso `jugado` si pertenecen a alguno de los clubs
 *     que lo disputaron.
 *
 * El conteo es a nivel de PARTIDO (COUNT DISTINCT del id del partido), de modo que
 * varias filas de alineación o de plantilla nunca inflan el número.
 *
 * Es un dato derivado: no requiere solicitud ni aceptación. Sobre él se habilitan
 * acciones directas (retar a un amistoso, invitar al equipo) en la ficha del jugador.
 */
class PlayedWithService
{
    /**
     * Jugadores con quienes `$user` compartió cancha, ordenados por cantidad de
     * partidos compartidos (desc) y nombre. Cada fila es un objeto
     * {user, shared} donde `shared` es el total combinado torneos + amistosos.
     *
     * Coste: 3 queries fijas (torneos + amistosos + carga de usuarios), sin
     * importar cuántos jugadores compartidos haya.
     */
    public function sharedPlayers(User $user): Collection
    {
        $counts = $this->mergeCounts(
            $this->tournamentSharedCounts($user->id),
            $this->friendlySharedCounts($user->id),
        );

        if (empty($counts)) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', array_keys($counts))
            ->get(['id', 'name', 'futgo_id', 'avatar_url', 'play_level', 'city'])
            ->keyBy('id');

        return collect($counts)
            ->map(fn (int $shared, int $otherId) => (object) [
                'user'   => $users->get($otherId),
                'shared' => $shared,
            ])
            ->filter(fn ($row) => $row->user !== null)
            ->sortBy([
                fn ($a, $b) => $b->shared <=> $a->shared,
                fn ($a, $b) => strcmp($a->user->name ?? '', $b->user->name ?? ''),
            ])
            ->values();
    }

    /**
     * Cantidad de partidos que `$a` y `$b` compartieron (torneos + amistosos).
     * Reusa las mismas queries de agregación, acotadas al otro jugador.
     */
    public function sharedCount(User $a, User $b): int
    {
        $tournament = $this->tournamentSharedCounts($a->id, $b->id);
        $friendly   = $this->friendlySharedCounts($a->id, $b->id);

        return (int) ($tournament[$b->id] ?? 0) + (int) ($friendly[$b->id] ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fuentes de agregación (una query cada una)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Co-participación en alineaciones de torneo. Self-join de `match_lineups`
     * sobre el mismo `match_id`; cuenta partidos distintos por jugador rival.
     *
     * @return array<int,int> [other_user_id => partidos_compartidos]
     */
    private function tournamentSharedCounts(int $userId, ?int $onlyOther = null): array
    {
        return DB::table('match_lineups as ml_me')
            ->join('team_players as tp_me', 'tp_me.id', '=', 'ml_me.team_player_id')
            ->join('match_lineups as ml_other', 'ml_other.match_id', '=', 'ml_me.match_id')
            ->join('team_players as tp_other', 'tp_other.id', '=', 'ml_other.team_player_id')
            ->where('tp_me.user_id', $userId)
            ->whereNotNull('tp_other.user_id')
            ->whereColumn('tp_other.user_id', '!=', 'tp_me.user_id')
            ->when($onlyOther, fn ($q) => $q->where('tp_other.user_id', $onlyOther))
            ->groupBy('tp_other.user_id')
            ->select('tp_other.user_id as other_id', DB::raw('COUNT(DISTINCT ml_me.match_id) as shared'))
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->other_id => (int) $row->shared])
            ->all();
    }

    /**
     * Co-participación en amistosos jugados, vía plantilla de los dos clubs.
     * Self-join de `club_players` (club ∈ {local, visitante}) sobre el mismo
     * amistoso; cuenta amistosos distintos por jugador.
     *
     * @return array<int,int> [other_user_id => amistosos_compartidos]
     */
    private function friendlySharedCounts(int $userId, ?int $onlyOther = null): array
    {
        return DB::table('friendly_matches as fm')
            ->join('club_players as cp_me', function ($join) {
                $join->on(function ($q) {
                    $q->on('cp_me.club_id', '=', 'fm.home_club_id')
                      ->orOn('cp_me.club_id', '=', 'fm.away_club_id');
                });
            })
            ->join('club_players as cp_other', function ($join) {
                $join->on(function ($q) {
                    $q->on('cp_other.club_id', '=', 'fm.home_club_id')
                      ->orOn('cp_other.club_id', '=', 'fm.away_club_id');
                });
            })
            ->where('fm.status', FriendlyMatch::STATUS_JUGADO)
            ->where('cp_me.user_id', $userId)
            ->whereNotNull('cp_other.user_id')
            ->whereColumn('cp_other.user_id', '!=', 'cp_me.user_id')
            ->when($onlyOther, fn ($q) => $q->where('cp_other.user_id', $onlyOther))
            ->groupBy('cp_other.user_id')
            ->select('cp_other.user_id as other_id', DB::raw('COUNT(DISTINCT fm.id) as shared'))
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->other_id => (int) $row->shared])
            ->all();
    }

    /** Suma dos mapas [user_id => count]. */
    private function mergeCounts(array $a, array $b): array
    {
        foreach ($b as $id => $count) {
            $a[$id] = ($a[$id] ?? 0) + $count;
        }

        return $a;
    }
}
