<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\CredentialValidation;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Services\Torneos\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Sesión D — Credencial QR antifraude: identificador FUTGO único, credencial
 * digital con QR y validación por árbitros (QR o ingreso manual) con auditoría.
 */
class CredentialQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeReferee(): User
    {
        return User::factory()->create(['role' => 'user',]);
    }

    private function makePlayer(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            ['role' => 'user',],
            $attrs
        ));
    }

    /** Crea un torneo en curso con el jugador dado inscrito y ACTIVO en un equipo. */
    private function tournamentWithActivePlayer(User $player, string $status = 'in_progress'): Tournament
    {
        $tournament = Tournament::create([
            'name' => 'Copa ' . uniqid(), 'slug' => 'copa-' . uniqid(),
            'sport' => 'futbol', 'status' => $status, 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1,
            'max_teams' => 4, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $player->id,
        ]);

        $team = Team::create([
            'tournament_id' => $tournament->id, 'captain_user_id' => $player->id,
            'name' => 'Equipo ' . uniqid(), 'status' => 'approved',
        ]);
        TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $player->id,
            'is_captain' => true, 'status' => 'active',
        ]);

        return $tournament;
    }

    // ── 1. Identificador único ────────────────────────────────────────────

    public function test_cada_jugador_recibe_un_futgo_id_unico_sin_colisiones(): void
    {
        $ids = [];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = $this->makePlayer()->futgo_id;
        }

        // Todos presentes, con formato válido y sin colisiones.
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^FG-[23456789ABCDEFGHJKMNPQRSTVWXYZ]{6}$/', $id);
        }
        $this->assertCount(25, array_unique($ids), 'Hubo colisiones de identificador FUTGO.');
    }

    // ── 2. Credencial digital ─────────────────────────────────────────────

    public function test_la_credencial_muestra_foto_nombre_e_identificador(): void
    {
        $player = $this->makePlayer([
            'name' => 'Lionel Pérez',
            'avatar_url' => 'https://futgo.test/storage/avatars/foto.png',
        ]);

        $res = $this->actingAs($player)->get(route('torneos.credencial'));

        $res->assertOk();
        $res->assertSee('Lionel Pérez');
        $res->assertSee($player->futgo_id);
        $res->assertSee('foto.png', false);      // la foto se renderiza
        $res->assertSee('<svg', false);           // el QR se renderiza como SVG
    }

    // ── 3. QR válido identifica al jugador correcto ───────────────────────

    public function test_el_qr_codifica_un_valor_que_identifica_al_jugador_correcto(): void
    {
        $player = $this->makePlayer();
        $url = CredentialService::qrUrlFor($player);

        // El QR lleva el identificador y una firma válida verificable.
        $this->assertStringContainsString($player->futgo_id, $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertTrue(CredentialService::verify($q['fg'], $q['sig']));

        // Escanear ese QR (GET con fg+sig) identifica al jugador correcto.
        $referee = $this->makeReferee();
        $this->tournamentWithActivePlayer($player);

        $res = $this->actingAs($referee)->get($url);
        $res->assertOk();
        $res->assertSee($player->name);
        $res->assertSee($player->futgo_id);
        $res->assertSee('Firma del QR verificada');
    }

    // ── 4. Validación de habilitado ───────────────────────────────────────

    public function test_validacion_de_jugador_habilitado_retorna_habilitado(): void
    {
        $referee = $this->makeReferee();
        $player = $this->makePlayer();
        $tournament = $this->tournamentWithActivePlayer($player, 'in_progress');

        $res = $this->actingAs($referee)->post(route('torneos.validar.run'), [
            'fg' => $player->futgo_id,
            'tournament_id' => $tournament->id,
        ]);

        $res->assertOk();
        $res->assertSee('HABILITADO');

        $this->assertDatabaseHas('credential_validations', [
            'futgo_id' => $player->futgo_id,
            'validated_user_id' => $player->id,
            'tournament_id' => $tournament->id,
            'result' => 'habilitado',
            'method' => 'manual',
        ]);
    }

    // ── 5. Validación de no inscrito ──────────────────────────────────────

    public function test_validacion_de_jugador_no_inscrito_retorna_no_habilitado(): void
    {
        $referee = $this->makeReferee();
        $outsider = $this->makePlayer();
        // El torneo pertenece a OTRO jugador; el outsider no está inscrito.
        $tournament = $this->tournamentWithActivePlayer($this->makePlayer(), 'in_progress');

        $res = $this->actingAs($referee)->post(route('torneos.validar.run'), [
            'fg' => $outsider->futgo_id,
            'tournament_id' => $tournament->id,
        ]);

        $res->assertOk();
        $res->assertSee('NO HABILITADO');

        $this->assertDatabaseHas('credential_validations', [
            'futgo_id' => $outsider->futgo_id,
            'validated_user_id' => $outsider->id,
            'tournament_id' => $tournament->id,
            'result' => 'no_habilitado',
        ]);
    }

    // ── 6. Privacidad del QR ──────────────────────────────────────────────

    public function test_el_qr_no_contiene_datos_personales_sensibles(): void
    {
        $player = $this->makePlayer([
            'name' => 'Diego Maradona',
            'email' => 'diego@futgo.test',
            'document' => '10101010',
        ]);

        $url = CredentialService::qrUrlFor($player);
        $svg = CredentialService::qrSvgFor($player);

        foreach ([$url, $svg] as $payload) {
            $this->assertStringNotContainsString('Diego', $payload);
            $this->assertStringNotContainsString('diego@futgo.test', $payload);
            $this->assertStringNotContainsString('10101010', $payload);
        }
        // Solo viaja el identificador público.
        $this->assertStringContainsString($player->futgo_id, $url);
    }

    // ── 7. Validación manual sin cámara ───────────────────────────────────

    public function test_validacion_manual_por_identificador_funciona_sin_camara(): void
    {
        $referee = $this->makeReferee();
        $player = $this->makePlayer(['name' => 'Manuel Sintarjeta']);
        $tournament = $this->tournamentWithActivePlayer($player, 'in_progress');

        // Ingreso manual: solo el identificador, SIN firma ni QR.
        $res = $this->actingAs($referee)->post(route('torneos.validar.run'), [
            'fg' => $player->futgo_id,
            'tournament_id' => $tournament->id,
        ]);

        $res->assertOk();
        $res->assertSee('Manuel Sintarjeta');
        $res->assertSee('HABILITADO');

        $this->assertDatabaseHas('credential_validations', [
            'futgo_id' => $player->futgo_id,
            'method' => 'manual',
            'result' => 'habilitado',
        ]);
    }

    // ── 8. Identificador inexistente + auditoría ──────────────────────────

    public function test_identificador_inexistente_retorna_no_encontrado_y_se_audita(): void
    {
        $referee = $this->makeReferee();

        $res = $this->actingAs($referee)->post(route('torneos.validar.run'), [
            'fg' => 'FG-ZZZZZZ',
        ]);

        $res->assertOk();
        $res->assertSee('NO ENCONTRADO');

        $row = CredentialValidation::where('futgo_id', 'FG-ZZZZZZ')->first();
        $this->assertNotNull($row);
        $this->assertSame('no_encontrado', $row->result);
        $this->assertNull($row->validated_user_id);
        $this->assertSame($referee->id, $row->validated_by_user_id);  // quién validó
        $this->assertNotNull($row->created_at);                        // cuándo
    }

    // ── 9. Carga inicial sin parámetros (bug fix H17) ────────────────────

    public function test_pagina_validar_carga_sin_error_cuando_no_hay_parametros_fg(): void
    {
        // Antes del fix: "Trying to access array offset on null" al acceder a
        // $result['tournament'] cuando $result era null (primera carga sin ?fg=).
        $referee = $this->makeReferee();

        $this->actingAs($referee)->get(route('torneos.validar'))
            ->assertOk()
            ->assertSee('Validar credencial');
    }

    // ── Control de acceso ─────────────────────────────────────────────────

    public function test_cualquier_usuario_autenticado_puede_validar_credenciales(): void
    {
        // Sin diferenciación de rol: cualquier usuario logueado puede validar
        // credenciales (la pantalla solo lista los torneos que administra).
        $player = $this->makePlayer();

        $this->actingAs($player)->get(route('torneos.validar'))
            ->assertOk();
    }
}
