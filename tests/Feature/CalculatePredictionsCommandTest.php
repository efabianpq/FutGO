<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalculatePredictionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function finishedGame(int $offHome, int $offAway): Game
    {
        return Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9001,
            'home_team' => 'Home', 'away_team' => 'Away',
            'match_datetime' => now()->subHours(2),
            'lock_datetime' => now()->subHours(2)->subMinutes(5),
            'venue' => 'Test', 'status' => 'finished',
            'home_score_official' => $offHome, 'away_score_official' => $offAway,
        ]);
    }

    public function test_calcula_puntos_y_actualiza_predicciones(): void
    {
        $game = $this->finishedGame(2, 1);
        $u1 = User::factory()->create(['is_active' => true, 'name' => 'A']); // exacto -> 5
        $u2 = User::factory()->create(['is_active' => true, 'name' => 'B']); // ganador + 1 -> 3
        $u3 = User::factory()->create(['is_active' => true, 'name' => 'C']); // solo ganador -> 2
        $u4 = User::factory()->create(['is_active' => true, 'name' => 'D']); // espejo -> 1
        $u5 = User::factory()->create(['is_active' => true, 'name' => 'E']); // sin coincidencias -> 0

        Prediction::create(['user_id' => $u1->id, 'match_id' => $game->id, 'home_score' => 2, 'away_score' => 1]);
        Prediction::create(['user_id' => $u2->id, 'match_id' => $game->id, 'home_score' => 3, 'away_score' => 1]);
        Prediction::create(['user_id' => $u3->id, 'match_id' => $game->id, 'home_score' => 3, 'away_score' => 0]);
        Prediction::create(['user_id' => $u4->id, 'match_id' => $game->id, 'home_score' => 1, 'away_score' => 2]);
        Prediction::create(['user_id' => $u5->id, 'match_id' => $game->id, 'home_score' => 0, 'away_score' => 4]);

        $this->artisan('predictions:calculate', ['match_id' => $game->id])->assertSuccessful();

        $this->assertSame(5, (int) Prediction::where(['user_id' => $u1->id, 'match_id' => $game->id])->value('points_earned'));
        $this->assertSame(3, (int) Prediction::where(['user_id' => $u2->id, 'match_id' => $game->id])->value('points_earned'));
        $this->assertSame(2, (int) Prediction::where(['user_id' => $u3->id, 'match_id' => $game->id])->value('points_earned'));
        $this->assertSame(1, (int) Prediction::where(['user_id' => $u4->id, 'match_id' => $game->id])->value('points_earned'));
        $this->assertSame(0, (int) Prediction::where(['user_id' => $u5->id, 'match_id' => $game->id])->value('points_earned'));
    }

    public function test_ranking_se_ordena_por_total_y_desempata_por_exactos(): void
    {
        $g1 = $this->finishedGame(2, 1);
        $g1->match_number = 9101;
        $g1->save();
        $g2 = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9102,
            'home_team' => 'H2', 'away_team' => 'A2',
            'match_datetime' => now()->subHours(1),
            'lock_datetime' => now()->subHours(1)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 3, 'away_score_official' => 0,
        ]);

        // Alice: dos exactos = 10 pts, 2 exactos
        $alice = User::factory()->create(['is_active' => true, 'name' => 'Alice']);
        Prediction::create(['user_id' => $alice->id, 'match_id' => $g1->id, 'home_score' => 2, 'away_score' => 1]);
        Prediction::create(['user_id' => $alice->id, 'match_id' => $g2->id, 'home_score' => 3, 'away_score' => 0]);

        // Bob: ganador correcto en ambos sin exactos = 4 pts, 0 exactos
        $bob = User::factory()->create(['is_active' => true, 'name' => 'Bob']);
        Prediction::create(['user_id' => $bob->id, 'match_id' => $g1->id, 'home_score' => 4, 'away_score' => 2]);
        Prediction::create(['user_id' => $bob->id, 'match_id' => $g2->id, 'home_score' => 5, 'away_score' => 1]);

        // Carol: un exacto + uno ganador = 5+2=7 pts, 1 exacto
        $carol = User::factory()->create(['is_active' => true, 'name' => 'Carol']);
        Prediction::create(['user_id' => $carol->id, 'match_id' => $g1->id, 'home_score' => 2, 'away_score' => 1]); // 5
        Prediction::create(['user_id' => $carol->id, 'match_id' => $g2->id, 'home_score' => 4, 'away_score' => 1]); // 2

        // Dan: igual a Carol en total (7 pts) pero con 0 exactos vs 1 → debería ir DESPUÉS de Carol
        // Dan: ganador+1 en ambos = 3+3=6. Necesito 7. Probemos: 5 + 2 = 7 con 1 exacto. Otra opción: ganador+1 +ganador+1 = 6. Hmm.
        // Hagamos Dan con 7 pts y 0 exactos: 3+3+1 no, son 2 partidos. Imposible 7 sin exactos en 2 partidos (3+3=6 max sin exactos).
        // Cambiamos: Dan con 7 pts y 1 exacto (igual a Carol): para tiebreak por id usamos sortBy user_id.
        $dan = User::factory()->create(['is_active' => true, 'name' => 'Dan']);
        Prediction::create(['user_id' => $dan->id, 'match_id' => $g1->id, 'home_score' => 4, 'away_score' => 1]); // ganador + 1 exacto = 3
        Prediction::create(['user_id' => $dan->id, 'match_id' => $g2->id, 'home_score' => 4, 'away_score' => 0]); // ganador + 1 exacto = 3

        // Dan = 6 pts. Hagamos Eve para probar desempate por exactos.
        // Eve: 6 pts también pero con DOS exactos → no, eso suma 10.
        // Dejemos: Carol 7-1exact, Dan 6-2exact (3+3=6 con 2 exactos en away).

        $this->artisan('predictions:calculate', ['match_id' => $g1->id])->assertSuccessful();
        $this->artisan('predictions:calculate', ['match_id' => $g2->id])->assertSuccessful();

        $rankings = DB::table('rankings')
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->orderBy('rankings.current_position')
            ->get(['users.name', 'rankings.total_points', 'rankings.exact_predictions', 'rankings.current_position']);

        $byName = $rankings->keyBy('name');

        $this->assertSame(10, (int) $byName['Alice']->total_points);
        $this->assertSame(2, (int) $byName['Alice']->exact_predictions);
        $this->assertSame(1, (int) $byName['Alice']->current_position);

        $this->assertSame(7, (int) $byName['Carol']->total_points);
        $this->assertSame(1, (int) $byName['Carol']->exact_predictions);
        $this->assertSame(2, (int) $byName['Carol']->current_position);

        $this->assertSame(6, (int) $byName['Dan']->total_points);
        $this->assertSame(0, (int) $byName['Dan']->exact_predictions);
        $this->assertSame(3, (int) $byName['Dan']->current_position);

        $this->assertSame(4, (int) $byName['Bob']->total_points);
        $this->assertSame(0, (int) $byName['Bob']->exact_predictions);
        $this->assertSame(4, (int) $byName['Bob']->current_position);
    }

    public function test_desempate_por_exactos_con_mismo_total(): void
    {
        $g = $this->finishedGame(2, 1);

        // Sin perdedor: necesito dos usuarios con mismo total pero diferentes exactos
        // U1: pred 2-1 (exacto) = 5 pts, 1 exacto
        // U2: ganador+1 en este (3 pts), y necesitamos otro partido para llegar a 5
        $g2 = Game::create([
            'phase' => 'grupos', 'group_name' => 'B', 'match_number' => 9201,
            'home_team' => 'X', 'away_team' => 'Y',
            'match_datetime' => now()->subHours(3),
            'lock_datetime' => now()->subHours(3)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 1, 'away_score_official' => 1,
        ]);

        $u1 = User::factory()->create(['is_active' => true, 'name' => 'ExactoUno']);
        Prediction::create(['user_id' => $u1->id, 'match_id' => $g->id, 'home_score' => 2, 'away_score' => 1]); // 5 exacto
        // No predice g2: 0 pts. Total 5, 1 exacto.

        $u2 = User::factory()->create(['is_active' => true, 'name' => 'SinExactos']);
        Prediction::create(['user_id' => $u2->id, 'match_id' => $g->id, 'home_score' => 3, 'away_score' => 1]);  // 3 pts (ganador+1)
        Prediction::create(['user_id' => $u2->id, 'match_id' => $g2->id, 'home_score' => 2, 'away_score' => 2]); // 2 pts (ganador empate)
        // Total 5, 0 exactos.

        $this->artisan('predictions:calculate', ['match_id' => $g->id])->assertSuccessful();
        $this->artisan('predictions:calculate', ['match_id' => $g2->id])->assertSuccessful();

        $a = DB::table('rankings')->where('user_id', $u1->id)->first();
        $b = DB::table('rankings')->where('user_id', $u2->id)->first();

        $this->assertSame(5, (int) $a->total_points);
        $this->assertSame(5, (int) $b->total_points);
        $this->assertSame(1, (int) $a->current_position, 'ExactoUno gana el desempate por exactos');
        $this->assertSame(2, (int) $b->current_position);
    }

    public function test_falla_si_partido_no_tiene_resultado_oficial(): void
    {
        $game = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9501,
            'home_team' => 'A', 'away_team' => 'B',
            'match_datetime' => now()->addHours(2),
            'lock_datetime' => now()->addHours(2)->subMinutes(5),
            'status' => 'upcoming',
        ]);

        $this->artisan('predictions:calculate', ['match_id' => $game->id])->assertFailed();
    }
}
