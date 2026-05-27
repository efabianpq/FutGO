<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_edita_equipo_de_eliminatoria_y_se_refleja_en_predictions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $game = Game::create([
            'phase' => 'octavos', 'match_number' => 9601,
            'home_team' => 'Clasificado A1', 'away_team' => 'Clasificado B2',
            'match_datetime' => now()->addDays(40),
            'lock_datetime' => now()->addDays(40)->subMinutes(5),
            'venue' => 'Por definir', 'status' => 'upcoming',
        ]);

        $this->actingAs($admin)->patch(route('admin.fixture.update', $game->id), [
            'home_team' => 'Colombia',
            'away_team' => 'Brasil',
            'home_flag' => '🇨🇴',
            'away_flag' => '🇧🇷',
            'match_date' => '2026-07-05',
            'match_time' => '15:00',
            'venue' => 'MetLife Stadium, Nueva Jersey',
        ])->assertRedirect(route('admin.fixture.index'));

        $game->refresh();
        $this->assertSame('Colombia', $game->home_team);
        $this->assertSame('Brasil', $game->away_team);
        $this->assertSame('🇨🇴', $game->home_flag);
        $this->assertSame('2026-07-05 15:00:00', $game->match_datetime->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-05 14:55:00', $game->lock_datetime->format('Y-m-d H:i:s'));

        // Se ve en la vista de pronósticos del participante
        $u = User::factory()->create(['is_active' => true]);
        $res = $this->actingAs($u)->get(route('predictions.index'));
        $res->assertOk()->assertSee('Colombia');
    }
}
