<?php

namespace Tests\Unit;

use App\Services\PredictionScoringService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PredictionScoringServiceTest extends TestCase
{
    public static function casos(): array
    {
        return [
            // [predHome, predAway, offHome, offAway, expectedPoints]

            // ---------- 5 pts: ambos marcadores exactos ----------
            'exactos local victoria' => [2, 1, 2, 1, 5],
            'exactos visitante victoria' => [0, 3, 0, 3, 5],
            'exactos empate 0-0' => [0, 0, 0, 0, 5],
            'exactos empate 2-2' => [2, 2, 2, 2, 5],

            // ---------- 3 pts: ganador correcto + 1 exacto en su lado ----------
            'ganador correcto + away exacto' => [2, 1, 3, 1, 3],
            'ganador correcto + home exacto' => [2, 1, 2, 0, 3],
            'ganador visitante + away exacto' => [1, 3, 2, 3, 3],

            // ---------- 2 pts: solo ganador correcto ----------
            'ganador correcto sin exactos local' => [2, 0, 3, 1, 2],
            'ganador correcto sin exactos visitante' => [0, 2, 1, 4, 2],
            'ganador correcto empate sin exactos' => [1, 1, 2, 2, 2],

            // ---------- 1 pt: ganador incorrecto + EXACTO MISMO LADO ----------
            'pred empate vs gana local con home exacto' => [1, 1, 1, 2, 1],
            'pred local gana vs empate con home exacto' => [1, 0, 1, 1, 1],
            'pred 0-0 vs gana local 1-0 — away 0 exacto' => [0, 0, 1, 0, 1],
            'pred 2-0 vs empate 2-2 — home 2 exacto' => [2, 0, 2, 2, 1],
            'pred 0-2 vs empate 0-0 — home 0 exacto' => [0, 2, 0, 0, 1],

            // ---------- 0 pts: ganador incorrecto + ningún exacto en su lado ----------
            // Espejo/cross-match: NO da 1 pt
            'invertido espejo 2-1 vs 1-2 NO suma' => [2, 1, 1, 2, 0],
            'invertido 3-1 vs 1-3 NO suma' => [3, 1, 1, 3, 0],
            // Sin coincidencias
            'sin coincidencias gana local vs gana visita' => [2, 0, 1, 3, 0],
            'sin coincidencias empate predicho vs gana local' => [3, 3, 1, 0, 0],
            'sin coincidencias gana visita vs gana local' => [0, 5, 4, 2, 0],
        ];
    }

    #[DataProvider('casos')]
    public function test_calcula_puntos(int $ph, int $pa, int $oh, int $oa, int $esperado): void
    {
        $service = new PredictionScoringService();
        $this->assertSame($esperado, $service->calculate($ph, $pa, $oh, $oa));
    }
}
