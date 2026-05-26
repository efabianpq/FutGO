<?php

namespace App\Services;

class PredictionScoringService
{
    /**
     * Calcula los puntos de un pronóstico según la tabla de puntos del documento:
     *
     *  5 pts: ambos marcadores exactos (lado contra lado).
     *  3 pts: ganador correcto + al menos un marcador exacto en su lado.
     *  2 pts: ganador correcto, ningún marcador exacto en su lado.
     *  1 pt : ganador incorrecto, pero al menos uno de los goles predichos
     *         aparece en el resultado (en cualquier posición — ej. pred 2-1, res 1-2).
     *  0 pts: ningún criterio cumplido.
     *
     * Solo cuenta el tiempo reglamentario; las tandas de penales no entran.
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

        // Ganador incorrecto: 1 pt si al menos un número predicho aparece en el resultado oficial
        $offScores = [$offHome, $offAway];
        if (in_array($predHome, $offScores, true) || in_array($predAway, $offScores, true)) {
            return 1;
        }

        return 0;
    }
}
