<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoMatchesSeeder extends Seeder
{
    /**
     * Resultados oficiales de los 15 primeros partidos para el demo.
     * Mezcla pedida:
     *   - 3 ajustados (0-0 o 1-0): #5, #11, #12
     *   - 4 empates en tiempo reglamentario: #2, #8, #12, #14
     *   - 3 goleadas (diferencia ≥ 3): #3, #6, #13
     *   - 5+ normales (2-1, 1-0, 2-0): #1, #4, #7, #9, #10, #15
     */
    public const RESULTS = [
        1  => [2, 1],  // México 2-1 Sudáfrica         (normal)
        2  => [1, 1],  // Corea del Sur 1-1 Rep. Checa (empate)
        3  => [3, 0],  // Canadá 3-0 Bosnia y H.       (goleada)
        4  => [2, 0],  // EE.UU. 2-0 Paraguay          (normal)
        5  => [0, 0],  // Qatar 0-0 Suiza              (ajustado + empate)
        6  => [4, 1],  // Brasil 4-1 Marruecos         (goleada)
        7  => [1, 2],  // Haití 1-2 Escocia            (normal visitante)
        8  => [1, 1],  // Australia 1-1 Turquía        (empate)
        9  => [3, 1],  // Alemania 3-1 Curazao         (normal home)
        10 => [2, 1],  // Países Bajos 2-1 Japón       (normal)
        11 => [1, 0],  // Costa de Marfil 1-0 Ecuador  (ajustado)
        12 => [0, 0],  // Suecia 0-0 Túnez             (ajustado + empate)
        13 => [3, 0],  // España 3-0 Cabo Verde        (goleada)
        14 => [2, 2],  // Bélgica 2-2 Egipto           (empate)
        15 => [0, 1],  // Arabia Saudita 0-1 Uruguay   (normal visitante)
    ];

    public function run(): void
    {
        $now = Carbon::now();

        foreach (self::RESULTS as $matchNumber => [$h, $a]) {
            // Distribuir las fechas: del #1 (hace ~45h) al #15 (hace ~3h)
            $hoursAgo = (16 - $matchNumber) * 3;
            $datetime = $now->copy()->subHours($hoursAgo);

            DB::table('matches')
                ->where('match_number', $matchNumber)
                ->update([
                    'status' => 'finished',
                    'home_score_official' => $h,
                    'away_score_official' => $a,
                    'match_datetime' => $datetime,
                    'lock_datetime' => $datetime->copy()->subMinutes(5),
                    'updated_at' => $now,
                ]);
        }
    }
}
