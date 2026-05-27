<?php

namespace App\Services;

class PredictionScoringService
{
    /**
     * Calcula los puntos de un pronóstico según la tabla de puntos del documento.
     * Solo cuenta el tiempo reglamentario; las tandas de penales no entran.
     *
     *  5 pts: ambos marcadores exactos (home y away, cada uno en su lado).
     *  3 pts: ganador correcto Y al menos un marcador exacto en su lado.
     *  2 pts: solo ganador correcto, ningún marcador exacto en su lado.
     *  1 pt : ganador incorrecto, pero acertó un marcador del mismo equipo
     *         (home_pred == home_oficial XOR away_pred == away_oficial).
     *  0 pts: ningún criterio cumplido.
     *
     * Importante: NO cuenta el "espejo" cruzado. Ejemplo:
     *   pred 1-2 vs resultado 2-1 → 0 pts (ningún lado coincide en su posición).
     *
     * Para empates: si pred home == pred away y oficial home == oficial away,
     * "ganador" se considera correcto (= empate).
     */
    public function calculate(int $predHome, int $predAway, int $offHome, int $offAway): int
    {
        $exactHome = $predHome === $offHome;
        $exactAway = $predAway === $offAway;

        if ($exactHome && $exactAway) {
            return 5;
        }

        $predWinner = $predHome <=> $predAway;
        $offWinner = $offHome <=> $offAway;
        $winnerCorrect = $predWinner === $offWinner;

        if ($winnerCorrect && ($exactHome || $exactAway)) {
            return 3;
        }

        if ($winnerCorrect) {
            return 2;
        }

        // Ganador incorrecto: 1 pt solo si acertó un marcador en su lado correspondiente.
        if ($exactHome || $exactAway) {
            return 1;
        }

        return 0;
    }
}
