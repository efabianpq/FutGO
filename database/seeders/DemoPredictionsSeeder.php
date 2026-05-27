<?php

namespace Database\Seeders;

use App\Services\PredictionScoringService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoPredictionsSeeder extends Seeder
{
    /**
     * Distribución intencional de aciertos por usuario sobre los 15 partidos finalizados.
     * Mapeo de puntos:
     *   exact  → 5 pts (marcador exacto en ambos lados)
     *   winner → 2 pts (ganador correcto, ningún marcador exacto en su lado)
     *   fail   → 0 pts (ningún criterio cumplido)
     *
     * Totales esperados de puntos:
     *   Carlos    = 6×5 + 5×2 + 4×0 = 40 pts (6 exactos)
     *   María     = 5×5 + 6×2 + 4×0 = 37 pts (5 exactos)
     *   Andrés    = 4×5 + 7×2 + 4×0 = 34 pts (4 exactos)
     *   Laura     = 4×5 + 5×2 + 6×0 = 30 pts (4 exactos)
     *   Juan      = 3×5 + 6×2 + 6×0 = 27 pts (3 exactos)
     *   Sofía     = 3×5 + 5×2 + 7×0 = 25 pts (3 exactos)
     *   Diego     = 2×5 + 6×2 + 7×0 = 22 pts (2 exactos)
     *   Valentina = 2×5 + 4×2 + 9×0 = 18 pts (2 exactos)
     *   Sebastián = 1×5 + 5×2 + 9×0 = 15 pts (1 exacto)
     *   Camila    = 1×5 + 3×2 + 11×0 = 11 pts (1 exacto)
     */
    private const DISTRIBUTION = [
        'carlos@demo.com'    => ['exact' => 6, 'winner' => 5, 'fail' => 4],
        'maria@demo.com'     => ['exact' => 5, 'winner' => 6, 'fail' => 4],
        'andres@demo.com'    => ['exact' => 4, 'winner' => 7, 'fail' => 4],
        'laura@demo.com'     => ['exact' => 4, 'winner' => 5, 'fail' => 6],
        'juan@demo.com'      => ['exact' => 3, 'winner' => 6, 'fail' => 6],
        'sofia@demo.com'     => ['exact' => 3, 'winner' => 5, 'fail' => 7],
        'diego@demo.com'     => ['exact' => 2, 'winner' => 6, 'fail' => 7],
        'valentina@demo.com' => ['exact' => 2, 'winner' => 4, 'fail' => 9],
        'sebastian@demo.com' => ['exact' => 1, 'winner' => 5, 'fail' => 9],
        'camila@demo.com'    => ['exact' => 1, 'winner' => 3, 'fail' => 11],
    ];

    public function run(): void
    {
        $now = Carbon::now();
        $scorer = app(PredictionScoringService::class);

        $finished = DB::table('matches')
            ->whereBetween('match_number', [1, 15])
            ->orderBy('match_number')
            ->get(['id', 'match_number', 'home_score_official', 'away_score_official']);

        $emails = array_keys(self::DISTRIBUTION);

        // ---- Predictions de matches 1..15 (calculables) ----
        foreach ($emails as $userIdx => $email) {
            $userId = (int) DB::table('users')->where('email', $email)->value('id');
            $dist = self::DISTRIBUTION[$email];
            $pattern = $this->pickPattern($finished->count(), $dist['exact'], $dist['winner'], $userIdx);

            foreach ($finished as $matchIdx => $match) {
                $targetPts = $pattern[$matchIdx];
                [$ph, $pa] = $this->generatePred(
                    $scorer,
                    (int) $match->home_score_official,
                    (int) $match->away_score_official,
                    $targetPts,
                    $userIdx * 13 + $matchIdx * 7
                );

                DB::table('predictions')->updateOrInsert(
                    ['user_id' => $userId, 'match_id' => $match->id],
                    [
                        'home_score' => $ph,
                        'away_score' => $pa,
                        'is_locked' => false,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        // ---- Predictions de matches 16..35 (bloqueadas, esperando resultado) ----
        $upcomingLocked = DB::table('matches')
            ->whereBetween('match_number', [16, 35])
            ->orderBy('match_number')
            ->get(['id', 'match_number']);

        foreach ($emails as $userIdx => $email) {
            $userId = (int) DB::table('users')->where('email', $email)->value('id');

            foreach ($upcomingLocked as $matchIdx => $match) {
                // Score realista 0..4 con seed determinista
                $seed = $userIdx * 17 + $matchIdx * 23;
                $ph = $seed % 5;
                $pa = intdiv($seed, 5) % 5;

                DB::table('predictions')->updateOrInsert(
                    ['user_id' => $userId, 'match_id' => $match->id],
                    [
                        'home_score' => $ph,
                        'away_score' => $pa,
                        'is_locked' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * Devuelve un array indexado por posición del match donde cada valor es
     * el puntaje target (5, 2 o 0). Cada usuario tiene un offset distinto
     * para que la distribución sea variada partido a partido.
     */
    private function pickPattern(int $total, int $exactCount, int $winnerCount, int $userIdx): array
    {
        $offset = $userIdx * 3;
        $shuffled = [];
        for ($i = 0; $i < $total; $i++) {
            $shuffled[] = ($i + $offset) % $total;
        }

        $exacts = array_slice($shuffled, 0, $exactCount);
        $winners = array_slice($shuffled, $exactCount, $winnerCount);

        $pattern = array_fill(0, $total, 0);
        foreach ($exacts as $idx) $pattern[$idx] = 5;
        foreach ($winners as $idx) $pattern[$idx] = 2;

        return $pattern;
    }

    /**
     * Encuentra un pronóstico (home, away) en el rango [0..4] que produzca
     * exactamente $targetPts contra el resultado oficial. Usa $seed para
     * elegir entre múltiples candidatos válidos (variedad entre usuarios).
     */
    private function generatePred(
        PredictionScoringService $scorer,
        int $offH,
        int $offA,
        int $targetPts,
        int $seed
    ): array {
        if ($targetPts === 5) {
            return [$offH, $offA];
        }

        $candidates = [];
        for ($ph = 0; $ph <= 4; $ph++) {
            for ($pa = 0; $pa <= 4; $pa++) {
                if ($scorer->calculate($ph, $pa, $offH, $offA) === $targetPts) {
                    $candidates[] = [$ph, $pa];
                }
            }
        }

        if (empty($candidates)) {
            // Defensa: no debería pasar para target 0 o 2.
            return [$offA, $offH]; // espejo (forzosamente 0 pts en empate-vs-no-empate)
        }

        return $candidates[$seed % count($candidates)];
    }
}
