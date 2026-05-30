<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Standing;
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

        DB::transaction(function () use ($phase, $groups, $pointsWin, $pointsDraw, $pointsLoss, $tiebreakerOrder) {
            foreach ($groups as $group) {
                $teamIds = $group->teams()->pluck('teams.id')->all();

                if (empty($teamIds)) {
                    continue;
                }

                // ── 1. Eliminar standings previos de este grupo ──────────────
                Standing::where('phase_id', $phase->id)
                    ->where('group_id', $group->id)
                    ->delete();

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
                    $matches->all()
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
     * @return array<int,array>
     */
    private function sortByTiebreaker(array $teams, array $order, array $allMatches): array
    {
        usort($teams, function (array $a, array $b) use ($order, $allMatches): int {
            // Puntos siempre es la clave primaria
            if ($b['points'] !== $a['points']) {
                return $b['points'] <=> $a['points'];
            }

            foreach ($order as $criterion) {
                $cmp = match ($criterion) {
                    'points'          => 0,  // ya se comparó arriba
                    'goal_difference' => $b['goal_difference'] <=> $a['goal_difference'],
                    'goals_for'       => $b['goals_for'] <=> $a['goals_for'],
                    'wins'            => $b['won'] <=> $a['won'],
                    'head_to_head'    => $this->headToHead($a['team_id'], $b['team_id'], $allMatches),
                    'fair_play'       => 0,
                    'drawing'         => 0,
                    default           => 0,
                };

                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        return $teams;
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
