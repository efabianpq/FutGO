<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResultsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function pendingGame(): Game
    {
        return Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9501,
            'home_team' => 'Local', 'away_team' => 'Visita',
            'match_datetime' => now()->subMinutes(30),
            'lock_datetime' => now()->subMinutes(35),
            'status' => 'upcoming',
        ]);
    }

    public function test_guarda_resultado_y_calcula_puntos_y_ranking(): void
    {
        $admin = $this->admin();
        $game = $this->pendingGame();

        $u1 = User::factory()->create(['is_active' => true, 'name' => 'Exacto']);
        $u2 = User::factory()->create(['is_active' => true, 'name' => 'Solo Ganador']);

        Prediction::create(['user_id' => $u1->id, 'match_id' => $game->id, 'home_score' => 2, 'away_score' => 1]);
        Prediction::create(['user_id' => $u2->id, 'match_id' => $game->id, 'home_score' => 4, 'away_score' => 0]);

        $res = $this->actingAs($admin)
            ->post(route('admin.results.store', $game->id), [
                'home_score' => 2,
                'away_score' => 1,
            ]);

        $res->assertRedirect();
        $res->assertSessionHas('status', fn ($msg) =>
            str_contains($msg, '2 pronósticos')
            && str_contains($msg, '1×5pts')
            && str_contains($msg, '1×2pts')
        );

        $this->assertDatabaseHas('matches', [
            'id' => $game->id, 'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 1,
        ]);

        $this->assertSame(5, (int) Prediction::where(['user_id' => $u1->id, 'match_id' => $game->id])->value('points_earned'));
        $this->assertSame(2, (int) Prediction::where(['user_id' => $u2->id, 'match_id' => $game->id])->value('points_earned'));

        // ranking refleja los puntos
        $r1 = DB::table('rankings')->where('user_id', $u1->id)->first();
        $r2 = DB::table('rankings')->where('user_id', $u2->id)->first();
        $this->assertSame(5, (int) $r1->total_points);
        $this->assertSame(2, (int) $r2->total_points);
        $this->assertSame(1, (int) $r1->current_position);
        $this->assertSame(2, (int) $r2->current_position);
    }

    public function test_permite_recalcular_corrigiendo_marcador(): void
    {
        $admin = $this->admin();
        $game = $this->pendingGame();

        $user = User::factory()->create(['is_active' => true]);
        Prediction::create(['user_id' => $user->id, 'match_id' => $game->id, 'home_score' => 2, 'away_score' => 1]);

        // Primer guardado: 2-1 → 5 pts
        $this->actingAs($admin)->post(route('admin.results.store', $game->id), ['home_score' => 2, 'away_score' => 1]);
        $this->assertSame(5, (int) Prediction::where('user_id', $user->id)->value('points_earned'));

        // Corrección: 1-0 → solo ganador = 2 pts
        $this->actingAs($admin)->post(route('admin.results.store', $game->id), ['home_score' => 1, 'away_score' => 0]);
        $this->assertSame(2, (int) Prediction::where('user_id', $user->id)->value('points_earned'));
        $this->assertDatabaseHas('matches', ['id' => $game->id, 'home_score_official' => 1, 'away_score_official' => 0]);
    }
}
