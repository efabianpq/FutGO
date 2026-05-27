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

    public function test_ranking_redirige_a_login_si_no_autenticado(): void
    {
        $this->get(route('ranking.index'))->assertRedirect(route('login'));
    }

    public function test_ranking_redirige_a_activate_con_mensaje_si_no_activo(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $res = $this->actingAs($user)->get(route('ranking.index'));
        $res->assertRedirect(route('activate.show'));
        $res->assertSessionHas('status', fn ($m) => str_contains($m, 'Activá tu código de invitación para acceder al ranking'));
    }

    public function test_ranking_accesible_para_usuario_activo(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get(route('ranking.index'))->assertOk();
    }

    public function test_muestra_por_definir_cuando_acumulado_no_configurado(): void
    {
        Settings::clearPrizePool();
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('Por definir');
    }

    public function test_muestra_premios_cuando_acumulado_configurado(): void
    {
        Settings::setPrizePool(1_000_000);
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('ranking.index'));
        $response->assertOk();

        // JSON inicial dentro de la vista
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

        $res = $this->actingAs($a)->getJson(route('ranking.data'));
        $res->assertOk();

        $rows = $res->json('rows');
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame(5, $rows[0]['total_points']);
        $this->assertSame(1, $rows[0]['current_position']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame(2, $rows[1]['total_points']);
    }

    public function test_tabla_de_ranking_no_tiene_columna_premio_estimado(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $res = $this->actingAs($user)->get(route('ranking.index'));
        $res->assertOk();
        $res->assertDontSee('Premio Est.');
    }

    public function test_show_muestra_solo_partidos_finished_con_puntos(): void
    {
        // Partido finalizado con puntos
        $finished = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9301,
            'home_team' => 'Local Calc', 'away_team' => 'Visita Calc',
            'match_datetime' => now()->subHours(2),
            'lock_datetime' => now()->subHours(2)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 1,
        ]);

        // Partido pendiente (NO debe verse)
        $pending = Game::create([
            'phase' => 'grupos', 'group_name' => 'B', 'match_number' => 9302,
            'home_team' => 'Secreto Home', 'away_team' => 'Secreto Away',
            'match_datetime' => now()->addHours(5),
            'lock_datetime' => now()->addHours(5)->subMinutes(5),
            'status' => 'upcoming',
        ]);

        $u = User::factory()->create(['is_active' => true, 'name' => 'Pepito']);
        Prediction::create(['user_id' => $u->id, 'match_id' => $finished->id, 'home_score' => 2, 'away_score' => 1]);
        // Pronóstico secreto sobre el partido pendiente — NO debe aparecer
        Prediction::create(['user_id' => $u->id, 'match_id' => $pending->id, 'home_score' => 4, 'away_score' => 4]);

        Artisan::call('predictions:calculate', ['match_id' => $finished->id]);

        $res = $this->actingAs($u)->get(route('ranking.show', $u));
        $res->assertOk()
            ->assertSee('Pepito')
            ->assertSee('Local Calc')
            ->assertSee('2 - 1')
            ->assertSee('5 pts')
            ->assertDontSee('Secreto Home')
            ->assertDontSee('Secreto Away')
            ->assertDontSee('4 - 4')
            ->assertDontSee('Pendiente'); // ya no se muestra
    }

    public function test_show_incluye_resumen_y_boton_volver(): void
    {
        $g = Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9401,
            'home_team' => 'Home', 'away_team' => 'Away',
            'match_datetime' => now()->subHours(2),
            'lock_datetime' => now()->subHours(2)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 1,
        ]);

        $u = User::factory()->create(['is_active' => true, 'name' => 'Tester']);
        Prediction::create(['user_id' => $u->id, 'match_id' => $g->id, 'home_score' => 2, 'away_score' => 1]);
        Artisan::call('predictions:calculate', ['match_id' => $g->id]);

        $res = $this->actingAs($u)->get(route('ranking.show', $u));
        $res->assertOk()
            ->assertSee('Volver al Ranking')         // botón explícito
            ->assertSee('Resumen del participante')  // sección de resumen
            ->assertSee('Aprovechamiento')           // métrica nueva
            ->assertSee('Partidos jugados');         // tarjeta de partidos
    }

    public function test_show_muestra_mensaje_cuando_no_hay_partidos_finalizados(): void
    {
        $u = User::factory()->create(['is_active' => true, 'name' => 'Sin Partidos']);

        $this->actingAs($u)->get(route('ranking.show', $u))
            ->assertOk()
            ->assertSee('Aún no hay partidos finalizados para este participante');
    }

    public function test_ranking_solo_lista_usuarios_activos(): void
    {
        $active = User::factory()->create(['is_active' => true, 'name' => 'Activo']);
        $inactive = User::factory()->create(['is_active' => false, 'name' => 'Inactivo']);

        \DB::table('rankings')->insert([
            ['user_id' => $active->id, 'total_points' => 0, 'exact_predictions' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $inactive->id, 'total_points' => 99, 'exact_predictions' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = $this->actingAs($active)->getJson(route('ranking.data'));
        $names = collect($res->json('rows'))->pluck('name')->all();

        $this->assertContains('Activo', $names);
        $this->assertNotContains('Inactivo', $names);
    }
}
