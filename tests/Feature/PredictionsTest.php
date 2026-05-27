<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionsTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function openGame(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'phase' => 'grupos',
            'group_name' => 'A',
            'match_number' => 9001,
            'home_team' => 'Equipo Local',
            'away_team' => 'Equipo Visita',
            'home_flag' => '🏳️',
            'away_flag' => '🏳️',
            'match_datetime' => now()->addHours(2),
            'lock_datetime' => now()->addHours(2)->subMinutes(5),
            'venue' => 'Estadio Test',
            'status' => 'upcoming',
        ], $overrides));
    }

    public function test_index_solo_para_usuarios_activos(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        $this->actingAs($inactive)
            ->get(route('predictions.index'))
            ->assertRedirect(route('activate.show'));
    }

    public function test_guarda_pronostico_y_responde_json(): void
    {
        $user = $this->activeUser();
        $game = $this->openGame();

        $response = $this->actingAs($user)
            ->postJson(route('predictions.update', $game), [
                'home_score' => 3,
                'away_score' => 1,
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('predictions', [
            'user_id' => $user->id,
            'match_id' => $game->id,
            'home_score' => 3,
            'away_score' => 1,
            'is_locked' => false,
        ]);
    }

    public function test_actualiza_pronostico_existente_sin_duplicar(): void
    {
        $user = $this->activeUser();
        $game = $this->openGame();

        $this->actingAs($user)->postJson(route('predictions.update', $game), [
            'home_score' => 1, 'away_score' => 1,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('predictions.update', $game), [
            'home_score' => 2, 'away_score' => 0,
        ])->assertOk();

        $this->assertSame(1, Prediction::where([
            'user_id' => $user->id, 'match_id' => $game->id,
        ])->count());

        $p = Prediction::where(['user_id' => $user->id, 'match_id' => $game->id])->first();
        $this->assertSame(2, $p->home_score);
        $this->assertSame(0, $p->away_score);
    }

    public function test_rechaza_pronostico_si_el_partido_esta_bloqueado(): void
    {
        $user = $this->activeUser();
        $game = $this->openGame([
            'match_datetime' => now()->subMinutes(2),
            'lock_datetime' => now()->subMinutes(7),
            'status' => 'live',
        ]);

        $this->actingAs($user)
            ->postJson(route('predictions.update', $game), [
                'home_score' => 1, 'away_score' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseMissing('predictions', [
            'user_id' => $user->id, 'match_id' => $game->id,
        ]);
    }

    public function test_valida_rangos_de_goles(): void
    {
        $user = $this->activeUser();
        $game = $this->openGame();

        $this->actingAs($user)
            ->postJson(route('predictions.update', $game), [
                'home_score' => -1, 'away_score' => 0,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('predictions.update', $game), [
                'home_score' => 0, 'away_score' => 99,
            ])
            ->assertStatus(422);
    }

    public function test_endpoint_states_devuelve_lock_state_correcto(): void
    {
        $user = $this->activeUser();
        $open = $this->openGame(['match_number' => 9101]);
        $closed = $this->openGame([
            'match_number' => 9102,
            'match_datetime' => now()->subMinutes(2),
            'lock_datetime' => now()->subMinutes(7),
        ]);

        $response = $this->actingAs($user)->getJson(route('predictions.states'));
        $response->assertOk();

        $data = $response->json('matches');
        $byId = collect($data)->keyBy('id');

        $this->assertFalse($byId[$open->id]['is_locked']);
        $this->assertTrue($byId[$closed->id]['is_locked']);
    }

    public function test_byMatch_rechaza_partidos_abiertos(): void
    {
        $user = $this->activeUser();
        $game = $this->openGame(); // futuro, no bloqueado

        $this->actingAs($user)
            ->getJson(route('predictions.byMatch', $game))
            ->assertStatus(403)
            ->assertJson(['ok' => false]);
    }

    public function test_byMatch_devuelve_todos_los_usuarios_activos_alfabetico_si_bloqueado(): void
    {
        $u1 = User::factory()->create(['is_active' => true, 'name' => 'Zoe']);
        $u2 = User::factory()->create(['is_active' => true, 'name' => 'Ana']);
        $u3 = User::factory()->create(['is_active' => true, 'name' => 'Bruno']);
        User::factory()->create(['is_active' => false, 'name' => 'Inactivo']);

        $game = $this->openGame([
            'match_datetime' => now()->subMinutes(2),
            'lock_datetime'  => now()->subMinutes(7),
            'status'         => 'live',
        ]);

        Prediction::create(['user_id' => $u2->id, 'match_id' => $game->id, 'home_score' => 2, 'away_score' => 1]);
        // u1 y u3 sin pronóstico

        $res = $this->actingAs($u1)->getJson(route('predictions.byMatch', $game));
        $res->assertOk();
        $names = collect($res->json('predictions'))->pluck('name')->all();

        $this->assertSame(['Ana', 'Bruno', 'Zoe'], $names);
        $this->assertNotContains('Inactivo', $names);

        $rows = collect($res->json('predictions'))->keyBy('name');
        $this->assertSame('2 - 1', $rows['Ana']['prediction']);
        $this->assertNull($rows['Bruno']['prediction']);
        $this->assertNull($rows['Ana']['points_earned']); // partido no finalizado
    }

    public function test_byMatch_finalizado_ordena_por_puntos_desc(): void
    {
        $alice = User::factory()->create(['is_active' => true, 'name' => 'Alice']);
        $bob = User::factory()->create(['is_active' => true, 'name' => 'Bob']);
        $carl = User::factory()->create(['is_active' => true, 'name' => 'Carl']);

        $game = $this->openGame([
            'match_datetime' => now()->subHours(2),
            'lock_datetime'  => now()->subHours(2)->subMinutes(5),
            'status'         => 'finished',
            'home_score_official' => 2,
            'away_score_official' => 1,
        ]);

        Prediction::create(['user_id' => $alice->id, 'match_id' => $game->id, 'home_score' => 2, 'away_score' => 1, 'points_earned' => 5]);
        Prediction::create(['user_id' => $bob->id,   'match_id' => $game->id, 'home_score' => 1, 'away_score' => 0, 'points_earned' => 2]);
        Prediction::create(['user_id' => $carl->id,  'match_id' => $game->id, 'home_score' => 0, 'away_score' => 2, 'points_earned' => 0]);

        $res = $this->actingAs($alice)->getJson(route('predictions.byMatch', $game));
        $res->assertOk();
        $names = collect($res->json('predictions'))->pluck('name')->all();

        $this->assertSame(['Alice', 'Bob', 'Carl'], $names);
        $rows = collect($res->json('predictions'))->keyBy('name');
        $this->assertSame(5, $rows['Alice']['points_earned']);
        $this->assertSame(0, $rows['Carl']['points_earned']);
    }

    public function test_usuarios_solo_ven_sus_propios_pronosticos(): void
    {
        $alice = $this->activeUser();
        $bob = $this->activeUser();
        $game = $this->openGame();

        Prediction::create([
            'user_id' => $alice->id, 'match_id' => $game->id,
            'home_score' => 4, 'away_score' => 2, 'is_locked' => false,
        ]);

        $bobView = $this->actingAs($bob)->get(route('predictions.index'));
        $bobView->assertOk();
        $bobView->assertDontSee('"home_score":4', false);
    }
}
