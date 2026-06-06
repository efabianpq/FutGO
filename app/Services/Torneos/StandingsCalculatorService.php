<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Standing;
use App\Models\Torneos\StandingDraw;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula la tabla de posiciones de una fase de grupos.
 *
 * Fuente de verdad: partidos con status='finished' (el sistema usa 'finished',
 * equivalente a 'completed' en la terminología del dominio).
 *
 * Siempre elimina los standings previos antes de recalcular para garantizar
 * consistencia total (no quedan filas "huérfanas" de equipos retirados, etc.).
 *
 * Puntos: usa exclusivamente points_win/draw/loss del torneo — nunca hardcodeados.
 * Desempate: respeta tiebreaker_order del torneo en el orden exacto configurado.
 * Criterios soportados: points, goal_difference, goals_for, wins, head_to_head.
 */
class StandingsCalculatorService
{
    public function recalculate(TournamentPhase $phase): void
    {
        if (! $phase->isGroups()) {
            return;
        }

        // Una fase cerrada queda congelada: sus standings no se recalculan.
        if ($phase->isCompleted()) {
            return;
        }

        $tournament   = $phase->tournament;
        $pointsWin    = (int) ($tournament->points_win  ?? 3);
        $pointsDraw   = (int) ($tournament->points_draw ?? 1);
        $pointsLoss   = (int) ($tournament->points_loss ?? 0);

        // tiebreaker_order puede ser null si no se configuró: se usa el default del modelo
        $tiebreakerOrder = (array) ($tournament->tiebreaker_order
            ?? $tournament->getDefaultTiebreakerOrder());

        $groups = $phase->groups()->with('teams')->orderBy('order')->get();
        $tournamentId = $tournament->id;

        DB::transaction(function () use ($phase, $groups, $pointsWin, $pointsDraw, $pointsLoss, $tiebreakerOrder, $tournamentId) {
            foreach ($groups as $group) {
                $teamIds = $group->teams()->pluck('teams.id')->all();

                if (empty($teamIds)) {
                    continue;
                }

                // ── 1. Eliminar standings y sorteos previos de este grupo ────
                Standing::where('phase_id', $phase->id)
                    ->where('group_id', $group->id)
                    ->delete();
                StandingDraw::where('phase_id', $phase->id)
                    ->where('group_id', $group->id)
                    ->delete();

                // Seed determinista del sorteo (reproducible entre recálculos) y
                // mapa de disciplina por equipo (para el criterio fair_play).
                $seed   = crc32($tournamentId . ':' . $phase->id . ':' . $group->id);
                $fpMap  = $this->disciplinaryMap($tournamentId, $teamIds);

                // ── 2. Solo partidos finished con marcador completo ──────────
                // 'finished' es el status que el sistema asigna al guardar resultado;
                // scheduled/live/postponed no se consideran.
                $matches = TournamentMatch::where('group_id', $group->id)
                    ->where('status', 'finished')
                    ->whereNotNull('home_score')
                    ->whereNotNull('away_score')
                    ->get();

                // ── 3. Inicializar acumuladores ──────────────────────────────
                $stats = [];
                foreach ($teamIds as $tid) {
                    $stats[$tid] = [
                        'team_id'       => $tid,
                        'played'        => 0,
                        'won'           => 0,
                        'drawn'         => 0,
                        'lost'          => 0,
                        'goals_for'     => 0,
                        'goals_against' => 0,
                        'goal_difference' => 0,
                        'points'        => 0,
                    ];
                }

                // ── 4. Acumular resultados ───────────────────────────────────
                foreach ($matches as $match) {
                    $home = (int) $match->home_team_id;
                    $away = (int) $match->away_team_id;

                    if (! isset($stats[$home], $stats[$away])) {
                        continue;
                    }

                    $hs = (int) $match->home_score;
                    $as = (int) $match->away_score;

                    $stats[$home]['played']++;
                    $stats[$away]['played']++;
                    $stats[$home]['goals_for']     += $hs;
                    $stats[$home]['goals_against'] += $as;
                    $stats[$away]['goals_for']     += $as;
                    $stats[$away]['goals_against'] += $hs;

                    if ($hs > $as) {
                        $stats[$home]['won']++;
                        $stats[$home]['points'] += $pointsWin;
                        $stats[$away]['lost']++;
                        $stats[$away]['points'] += $pointsLoss;
                    } elseif ($hs < $as) {
                        $stats[$away]['won']++;
                        $stats[$away]['points'] += $pointsWin;
                        $stats[$home]['lost']++;
                        $stats[$home]['points'] += $pointsLoss;
                    } else {
                        $stats[$home]['drawn']++;
                        $stats[$home]['points'] += $pointsDraw;
                        $stats[$away]['drawn']++;
                        $stats[$away]['points'] += $pointsDraw;
                    }
                }

                // ── 5. Diferencia de goles ───────────────────────────────────
                foreach ($stats as &$row) {
                    $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
                }
                unset($row);

                // ── 6. Ordenar con desempates ────────────────────────────────
                $sorted = $this->sortByTiebreaker(
                    array_values($stats),
                    $tiebreakerOrder,
                    $matches->all(),
                    $seed,
                    $fpMap
                );

                // ── 7. Insertar standings con posición ───────────────────────
                $now = now();
                foreach ($sorted as $position => $row) {
                    Standing::create([
                        'phase_id'           => $phase->id,
                        'group_id'           => $group->id,
                        'team_id'            => $row['team_id'],
                        'played'             => $row['played'],
                        'won'                => $row['won'],
                        'drawn'              => $row['drawn'],
                        'lost'               => $row['lost'],
                        'goals_for'          => $row['goals_for'],
                        'goals_against'      => $row['goals_against'],
                        'goal_difference'    => $row['goal_difference'],
                        'points'             => $row['points'],
                        'position'           => $position + 1,
                        'last_calculated_at' => $now,
                    ]);
                }

                // ── 8. Auditar el sorteo (solo empates absolutos) ────────────
                $this->recordDraws($phase, $group->id, $sorted, $tiebreakerOrder, $matches->all(), $fpMap, $seed);
            }
        });
    }

    // ─────────────────────────── Ordenamiento ────────────────────────────────

    /**
     * Ordena equipos por puntos DESC y luego por cada criterio en tiebreaker_order.
     *
     * Criterios soportados:
     *   points          → sin efecto real (ya se ordenó por puntos como clave primaria)
     *   goal_difference → diferencia de goles global
     *   goals_for       → goles a favor global
     *   wins            → victorias globales
     *   head_to_head    → resultado directo entre los dos equipos (pts → DG → GF)
     *   fair_play       → placeholder (sin datos en este modelo)
     *   drawing         → placeholder (sorteo; no altera orden)
     *
     * @param array<int,array>          $teams       Acumuladores con llaves de stats.
     * @param array<int,string>         $order       Criterios de desempate en orden exacto.
     * @param array<int,TournamentMatch> $allMatches  Para calcular head_to_head.
     * @param int                       $seed        Semilla determinista para el sorteo.
     * @param array<int,int>            $fpMap       Disciplina por team_id (menos es mejor).
     * @return array<int,array>
     */
    private function sortByTiebreaker(array $teams, array $order, array $allMatches, int $seed, array $fpMap): array
    {
        usort($teams, fn (array $a, array $b): int => $this->compareTeams($a, $b, $order, $allMatches, $seed, $fpMap));

        return $teams;
    }

    /** Comparación completa de dos equipos según puntos + tiebreaker_order. */
    private function compareTeams(array $a, array $b, array $order, array $allMatches, int $seed, array $fpMap): int
    {
        // Puntos siempre es la clave primaria.
        if ($b['points'] !== $a['points']) {
            return $b['points'] <=> $a['points'];
        }

        foreach ($order as $criterion) {
            $cmp = $this->compareByCriterion($criterion, $a, $b, $allMatches, $seed, $fpMap);
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }

    /**
     * Compara dos equipos por un criterio puntual.
     *   fair_play → menos disciplina (amarillas + 3·rojas) rankea mejor.
     *   drawing   → sorteo DETERMINISTA por seed (md5 estable) → reproducible.
     */
    private function compareByCriterion(string $criterion, array $a, array $b, array $allMatches, int $seed, array $fpMap): int
    {
        return match ($criterion) {
            'points'          => 0,  // ya se comparó arriba
            'goal_difference' => $b['goal_difference'] <=> $a['goal_difference'],
            'goals_for'       => $b['goals_for'] <=> $a['goals_for'],
            'wins'            => $b['won'] <=> $a['won'],
            'head_to_head'    => $this->headToHead($a['team_id'], $b['team_id'], $allMatches),
            'fair_play'       => ($fpMap[$a['team_id']] ?? 0) <=> ($fpMap[$b['team_id']] ?? 0),
            'drawing'         => strcmp(
                md5($seed . ':' . $a['team_id']),
                md5($seed . ':' . $b['team_id'])
            ),
            default           => 0,
        };
    }

    /**
     * Disciplina acumulada por equipo en el torneo: amarillas + 3·rojas.
     * Menos = mejor fair play. Usado por el criterio de desempate 'fair_play'.
     *
     * @return array<int,int>
     */
    private function disciplinaryMap(int $tournamentId, array $teamIds): array
    {
        $rows = DB::table('player_stats as ps')
            ->join('team_players as tp', 'tp.id', '=', 'ps.team_player_id')
            ->where('ps.tournament_id', $tournamentId)
            ->whereIn('tp.team_id', $teamIds)
            ->groupBy('tp.team_id')
            ->selectRaw('tp.team_id, COALESCE(SUM(ps.yellow_cards),0) y, COALESCE(SUM(ps.red_cards),0) r')
            ->get();

        $map = array_fill_keys($teamIds, 0);
        foreach ($rows as $r) {
            $map[$r->team_id] = (int) $r->y + 3 * (int) $r->r;
        }

        return $map;
    }

    /**
     * Registra en standing_draws SOLO los equipos cuya posición se resolvió por
     * sorteo (empate absoluto: iguales en puntos y en todos los criterios previos
     * a 'drawing'). El seed garantiza reproducibilidad entre recálculos.
     *
     * @param array<int,array> $sorted Equipos ya ordenados (con posición = índice).
     */
    private function recordDraws(TournamentPhase $phase, int $groupId, array $sorted, array $order, array $allMatches, array $fpMap, int $seed): void
    {
        $n = count($sorted);
        $i = 0;

        while ($i < $n) {
            // Extiende un "tie-set": equipos consecutivos iguales en todo menos el sorteo.
            $j = $i;
            while ($j + 1 < $n && $this->tiedExceptDrawing($sorted[$i], $sorted[$j + 1], $order, $allMatches, $fpMap)) {
                $j++;
            }

            // Si el set tiene más de un equipo, su orden lo decidió el sorteo → auditar.
            if ($j > $i) {
                for ($k = $i; $k <= $j; $k++) {
                    StandingDraw::create([
                        'phase_id'      => $phase->id,
                        'group_id'      => $groupId,
                        'team_id'       => $sorted[$k]['team_id'],
                        'seed'          => $seed,
                        'draw_position' => $k + 1,
                    ]);
                }
            }

            $i = $j + 1;
        }
    }

    /** ¿Dos equipos quedan empatados en puntos y en todos los criterios salvo 'drawing'? */
    private function tiedExceptDrawing(array $a, array $b, array $order, array $allMatches, array $fpMap): bool
    {
        if ($a['points'] !== $b['points']) {
            return false;
        }

        foreach ($order as $criterion) {
            if ($criterion === 'drawing') {
                continue;
            }
            if ($this->compareByCriterion($criterion, $a, $b, $allMatches, 0, $fpMap) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Comparación head-to-head entre dos equipos (solo sus partidos directos).
     *
     * Aplica en cascada:
     *   1. Puntos entre ellos
     *   2. Diferencia de goles entre ellos
     *   3. Goles a favor entre ellos
     *
     * Retorna negativo si A queda antes que B, positivo si B queda antes, 0 si empatan todo.
     */
    private function headToHead(int $teamA, int $teamB, array $matches): int
    {
        $aPoints = 0;
        $bPoints = 0;
        $aGF = 0;
        $bGF = 0;

        foreach ($matches as $m) {
            $home = (int) $m->home_team_id;
            $away = (int) $m->away_team_id;

            if (! (($home === $teamA && $away === $teamB) || ($home === $teamB && $away === $teamA))) {
                continue;
            }

            $hs = (int) $m->home_score;
            $as = (int) $m->away_score;

            // Goles a favor desde la perspectiva de cada equipo
            if ($home === $teamA) {
                $aGF += $hs;
                $bGF += $as;
            } else {
                $aGF += $as;
                $bGF += $hs;
            }

            // Puntos
            if ($hs > $as) {
                $winner = $home;
            } elseif ($as > $hs) {
                $winner = $away;
            } else {
                $aPoints++;
                $bPoints++;
                continue;
            }

            if ($winner === $teamA) {
                $aPoints += 3;
            } else {
                $bPoints += 3;
            }
        }

        // 1. Puntos directos
        if ($bPoints !== $aPoints) {
            return $bPoints <=> $aPoints;  // más puntos = antes (menor índice)
        }

        // 2. Diferencia de goles directa
        $aDiff = $aGF - $bGF;
        $bDiff = $bGF - $aGF;
        if ($bDiff !== $aDiff) {
            return $bDiff <=> $aDiff;
        }

        // 3. Goles a favor directos
        if ($bGF !== $aGF) {
            return $bGF <=> $aGF;
        }

        return 0;
    }
}
