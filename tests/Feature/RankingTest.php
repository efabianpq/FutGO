<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_es_publico_sin_autenticacion(): void
    {
        $this->get(route('ranking.index'))->assertOk();
    }

    public function test_muestra_por_definir_cuando_pozo_no_configurado(): void
    {
        Settings::clearPrizePool();
        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('Por definir');
    }

    public function test_muestra_premios_cuando_pozo_configurado(): void
    {
        Settings::setPrizePool(1_000_000);
        $response = $this->get(route('ranking.index'));
        $response->assertOk();

        // El JSON inicial dentro de la vista contiene los breakdowns
        $response->assertSee('600000', false); // 60%
        $response->assertSee('200000', false); // 20%
        $response->assertSee('100000', false); // 10%
    }

    public function test_data_endpoint_devuelve_filas_ordenadas(): void
    {
        $g = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9001,
            'home_team' => 'H', 'away_team' => 'A',
            'match_datetime' => now()->subHours(2),
            'lock_datetime' => now()->subHours(2)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 1,
        ]);

        $a = User::factory()->create(['is_active' => true, 'name' => 'Alice']);
        $b = User::factory()->create(['is_active' => true, 'name' => 'Bob']);

        Prediction::create(['user_id' => $a->id, 'match_id' => $g->id, 'home_score' => 2, 'away_score' => 1]); // 5
        Prediction::create(['user_id' => $b->id, 'match_id' => $g->id, 'home_score' => 1, 'away_score' => 0]); // 2

        Artisan::call('predictions:calculate', ['match_id' => $g->id]);

        $res = $this->getJson(route('ranking.data'));
        $res->assertOk();

        $rows = $res->json('rows');
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame(5, $rows[0]['total_points']);
        $this->assertSame(1, $rows[0]['current_position']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame(2, $rows[1]['total_points']);
    }

    public function test_vista_show_es_publica_y_muestra_auditoria(): void
    {
        $g = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9301,
            'home_team' => 'Local', 'away_team' => 'Visita',
            'home_flag' => '🏳️', 'away_flag' => '🏳️',
            'match_datetime' => now()->subHours(2),
            'lock_datetime' => now()->subHours(2)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 1,
        ]);

        $u = User::factory()->create(['is_active' => true, 'name' => 'Pepito']);
        Prediction::create(['user_id' => $u->id, 'match_id' => $g->id, 'home_score' => 2, 'away_score' => 1]);
        Artisan::call('predictions:calculate', ['match_id' => $g->id]);

        $res = $this->get(route('ranking.show', $u));
        $res->assertOk()
            ->assertSee('Pepito')
            ->assertSee('Local')
            ->assertSee('Visita')
            ->assertSee('2 - 1') // pronóstico y resultado oficial
            ->assertSee('5 pts');
    }

    public function test_show_marca_partidos_no_finalizados_como_pendiente(): void
    {
        $g = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9401,
            'home_team' => 'X', 'away_team' => 'Y',
            'match_datetime' => now()->addHours(5),
            'lock_datetime' => now()->addHours(5)->subMinutes(5),
            'status' => 'upcoming',
        ]);

        $u = User::factory()->create(['is_active' => true, 'name' => 'Sin Pronóstico']);

        $this->get(route('ranking.show', $u))
            ->assertOk()
            ->assertSee('Pendiente')
            ->assertSee('Sin pronóstico');
    }

    public function test_ranking_solo_lista_usuarios_activos(): void
    {
        $active = User::factory()->create(['is_active' => true, 'name' => 'Activo']);
        $inactive = User::factory()->create(['is_active' => false, 'name' => 'Inactivo']);

        // Forzar fila en rankings para ambos (simulando ensure)
        \DB::table('rankings')->insert([
            ['user_id' => $active->id, 'total_points' => 0, 'exact_predictions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $inactive->id, 'total_points' => 99, 'exact_predictions' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = $this->getJson(route('ranking.data'));
        $names = collect($res->json('rows'))->pluck('name')->all();

        $this->assertContains('Activo', $names);
        $this->assertNotContains('Inactivo', $names);
    }
}
