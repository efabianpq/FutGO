<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockPredictionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloquea_pronosticos_y_cambia_status_a_live(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $vencido = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9001,
            'home_team' => 'A', 'away_team' => 'B',
            'match_datetime' => now()->subMinutes(2),
            'lock_datetime' => now()->subMinutes(7),
            'status' => 'upcoming',
        ]);

        $futuro = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9002,
            'home_team' => 'C', 'away_team' => 'D',
            'match_datetime' => now()->addHours(3),
            'lock_datetime' => now()->addHours(3)->subMinutes(5),
            'status' => 'upcoming',
        ]);

        Prediction::create([
            'user_id' => $user->id, 'match_id' => $vencido->id,
            'home_score' => 1, 'away_score' => 0, 'is_locked' => false,
        ]);
        Prediction::create([
            'user_id' => $user->id, 'match_id' => $futuro->id,
            'home_score' => 2, 'away_score' => 1, 'is_locked' => false,
        ]);

        $this->artisan('predictions:lock')->assertSuccessful();

        $this->assertDatabaseHas('matches', ['id' => $vencido->id, 'status' => 'live']);
        $this->assertDatabaseHas('matches', ['id' => $futuro->id, 'status' => 'upcoming']);

        $this->assertDatabaseHas('predictions', [
            'user_id' => $user->id, 'match_id' => $vencido->id, 'is_locked' => true,
        ]);
        $this->assertDatabaseHas('predictions', [
            'user_id' => $user->id, 'match_id' => $futuro->id, 'is_locked' => false,
        ]);
    }

    public function test_no_toca_partidos_finalizados(): void
    {
        $game = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9003,
            'home_team' => 'X', 'away_team' => 'Y',
            'match_datetime' => now()->subDays(2),
            'lock_datetime' => now()->subDays(2)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 1,
        ]);

        $this->artisan('predictions:lock')->assertSuccessful();

        $this->assertDatabaseHas('matches', ['id' => $game->id, 'status' => 'finished']);
    }
}
