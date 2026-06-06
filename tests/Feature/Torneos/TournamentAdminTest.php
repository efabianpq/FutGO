<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentAdminTest extends TestCase
{
    use RefreshDatabase;

    private function torneoAdmin(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'role'      => 'torneo_admin',
            'modules'   => 'torneos',
        ]);
    }

    private function globalAdmin(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'role'      => 'admin',
            'modules'   => 'full',
        ]);
    }

    /** Crea un torneo y agrega al usuario dado como admin. */
    private function makeTournamentFor(User $admin, array $attrs = []): Tournament
    {
        $tournament = Tournament::create(array_merge([
            'name'               => 'Copa ' . uniqid(),
            'slug'               => 'copa-' . uniqid(),
            'sport'              => 'futbol',
            'status'             => 'draft',
            'format'             => 'groups_and_knockout',
            'groups_count'       => 2,
            'teams_per_group'    => 4,
            'classifies_per_group' => 2,
            'created_by_user_id' => $admin->id,
        ], $attrs));

        $tournament->admins()->attach($admin->id);

        return $tournament;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                 => 'Copa Apertura',
            'sport'                => 'futbol',
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 4,
            'classifies_per_group' => 2,
            // Config avanzada (Prompt 6B) — requeridos
            'visibility'           => 'public',
            'category'             => 'libre',
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'knockout_tiebreak'    => 'penalties',
            'min_players_per_team' => 7,
            'max_players_per_team' => 25,
            'match_duration'       => 90,
            'max_substitutions'    => 5,
        ], $overrides);
    }

    public function test_admin_torneos_index_redirige_a_vista_unificada(): void
    {
        // H3: "Gestión Torneos" se consolidó con "Mis Torneos" (torneos.index).
        $admin = $this->torneoAdmin();

        $this->actingAs($admin)
             ->get(route('admin.torneos.index'))
             ->assertRedirect(route('torneos.index'));
    }

    public function test_torneo_admin_ve_listado_de_sus_torneos(): void
    {
        $admin = $this->torneoAdmin();
        $t = $this->makeTournamentFor($admin, ['name' => 'Liga Propia']);

        // Vista unificada (H3): muestra la tarjeta con acciones Ver/Editar.
        $this->actingAs($admin)
             ->get(route('torneos.index'))
             ->assertOk()
             ->assertSee('Liga Propia');
    }

    public function test_torneo_admin_no_ve_torneos_de_otros_admins(): void
    {
        $adminA = $this->torneoAdmin();
        $adminB = $this->torneoAdmin();

        $this->makeTournamentFor($adminA, ['name' => 'Torneo Ajeno']);

        $this->actingAs($adminB)
             ->get(route('torneos.index'))
             ->assertOk()
             ->assertDontSee('Torneo Ajeno');
    }

    public function test_admin_global_ve_todos_los_torneos(): void
    {
        $adminA = $this->torneoAdmin();
        $this->makeTournamentFor($adminA, ['name' => 'Torneo De A']);

        $global = $this->globalAdmin();

        // El admin de plataforma ve TODOS los torneos en la vista unificada.
        $this->actingAs($global)
             ->get(route('torneos.index'))
             ->assertOk()
             ->assertSee('Torneo De A');
    }

    public function test_vista_unificada_muestra_acciones_ver_y_editar_para_admin(): void
    {
        // H3: los botones Ver/Editar aparecen solo si el usuario administra el torneo.
        $admin = $this->torneoAdmin();
        $this->makeTournamentFor($admin, ['name' => 'Torneo Gestionado']);

        $this->actingAs($admin)
             ->get(route('torneos.index'))
             ->assertOk()
             ->assertSee('Torneo Gestionado')
             ->assertSee('Editar');
    }

    public function test_jugador_no_ve_acciones_de_administracion(): void
    {
        // Un jugador (no admin del torneo) ve la tarjeta sin botón Editar.
        $admin  = $this->torneoAdmin();
        $player = User::factory()->create(['is_active' => true, 'role' => 'user', 'modules' => 'torneos']);

        $t = $this->makeTournamentFor($admin, ['name' => 'Torneo Jugado', 'status' => 'open']);
        $team = \App\Models\Torneos\Team::create([
            'tournament_id' => $t->id, 'captain_user_id' => $player->id,
            'name' => 'Equipo Jugador', 'status' => 'approved',
        ]);
        \App\Models\Torneos\TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $player->id, 'is_captain' => true, 'status' => 'active',
        ]);

        $this->actingAs($player)
             ->get(route('torneos.index'))
             ->assertOk()
             ->assertSee('Torneo Jugado')
             ->assertDontSee('Editar');
    }

    public function test_se_puede_crear_torneo_con_datos_validos(): void
    {
        $admin = $this->torneoAdmin();

        $this->actingAs($admin)
             ->post(route('admin.torneos.store'), $this->validPayload(['name' => 'Copa Verano']))
             ->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'name'   => 'Copa Verano',
            'status' => 'draft',
        ]);
    }

    public function test_no_se_puede_crear_con_datos_invalidos(): void
    {
        $admin = $this->torneoAdmin();

        $this->actingAs($admin)
             ->post(route('admin.torneos.store'), $this->validPayload(['name' => 'ab']))
             ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('tournaments', ['sport' => 'futbol', 'name' => 'ab']);
    }

    // ─── H4: logo y banner como imagen adjunta ──────────────────────────────────

    public function test_se_puede_subir_logo_y_banner_como_imagen(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = $this->torneoAdmin();

        $payload = $this->validPayload([
            'name'   => 'Copa Con Logo',
            'logo'   => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 200),
            'banner' => \Illuminate\Http\UploadedFile::fake()->image('banner.jpg', 800, 300),
        ]);

        $this->actingAs($admin)
             ->post(route('admin.torneos.store'), $payload)
             ->assertRedirect();

        $tournament = Tournament::where('name', 'Copa Con Logo')->firstOrFail();

        // Las URLs apuntan al disco público y los archivos existen.
        $this->assertNotNull($tournament->logo_url);
        $this->assertNotNull($tournament->banner_url);
        $this->assertStringStartsWith('/storage/torneos/', $tournament->logo_url);

        \Illuminate\Support\Facades\Storage::disk('public')
            ->assertExists(str_replace('/storage/', '', $tournament->logo_url));
        \Illuminate\Support\Facades\Storage::disk('public')
            ->assertExists(str_replace('/storage/', '', $tournament->banner_url));
    }

    public function test_logo_rechaza_archivo_que_no_es_imagen(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = $this->torneoAdmin();

        $payload = $this->validPayload([
            'name' => 'Copa Mal Logo',
            'logo' => \Illuminate\Http\UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($admin)
             ->post(route('admin.torneos.store'), $payload)
             ->assertSessionHasErrors('logo');
    }

    // ─── H5: valor de inscripción en formato divisa ─────────────────────────────

    public function test_registration_fee_se_guarda_como_entero_limpio(): void
    {
        $admin = $this->torneoAdmin();

        // El input formatea visualmente ($ 50.000) pero el hidden envía el entero limpio.
        $this->actingAs($admin)
             ->post(route('admin.torneos.store'), $this->validPayload([
                 'name'             => 'Copa Con Costo',
                 'registration_fee' => 50000,
             ]))
             ->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'name'             => 'Copa Con Costo',
            'registration_fee' => 50000,
        ]);
    }

    public function test_el_slug_se_genera_automaticamente(): void
    {
        $admin = $this->torneoAdmin();

        $this->actingAs($admin)
             ->post(route('admin.torneos.store'), $this->validPayload(['name' => 'Copa Apertura 2026']));

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Copa Apertura 2026',
            'slug' => 'copa-apertura-2026',
        ]);
    }

    public function test_el_creador_queda_en_tournament_admins_automaticamente(): void
    {
        $admin = $this->torneoAdmin();

        $this->actingAs($admin)
             ->post(route('admin.torneos.store'), $this->validPayload(['name' => 'Copa Creador']));

        $tournament = Tournament::where('name', 'Copa Creador')->firstOrFail();

        $this->assertDatabaseHas('tournament_admins', [
            'tournament_id' => $tournament->id,
            'user_id'       => $admin->id,
        ]);
    }

    public function test_no_se_puede_eliminar_torneo_en_in_progress_ni_finished(): void
    {
        // H8: se amplió la eliminación a draft y open; in_progress y finished siguen bloqueados.
        $admin = $this->torneoAdmin();

        foreach (['in_progress', 'finished'] as $status) {
            $t = $this->makeTournamentFor($admin, ['status' => $status]);

            $this->actingAs($admin)
                 ->delete(route('admin.torneos.destroy', $t))
                 ->assertRedirect();

            $this->assertDatabaseHas('tournaments', ['id' => $t->id]);
        }
    }

    public function test_se_puede_eliminar_torneo_en_open(): void
    {
        // H8: ahora también se puede eliminar torneos en estado "open" (inscripción).
        $admin = $this->torneoAdmin();
        $t = $this->makeTournamentFor($admin, ['status' => 'open']);

        $this->actingAs($admin)
             ->delete(route('admin.torneos.destroy', $t))
             ->assertRedirect(route('admin.torneos.index'));

        $this->assertDatabaseMissing('tournaments', ['id' => $t->id]);
    }

    public function test_se_puede_eliminar_torneo_en_draft(): void
    {
        $admin = $this->torneoAdmin();
        $t = $this->makeTournamentFor($admin, ['status' => 'draft']);

        $this->actingAs($admin)
             ->delete(route('admin.torneos.destroy', $t))
             ->assertRedirect(route('admin.torneos.index'));

        $this->assertDatabaseMissing('tournaments', ['id' => $t->id]);
    }

    public function test_el_cambio_de_estado_respeta_la_secuencia_correcta(): void
    {
        $admin = $this->torneoAdmin();
        $t = $this->makeTournamentFor($admin, ['status' => 'draft']);

        // Avance válido: draft → open
        $this->actingAs($admin)
             ->patch(route('admin.torneos.status', $t), ['status' => 'open'])
             ->assertRedirect();
        $this->assertEquals('open', $t->fresh()->status);

        // Retroceso inválido: open → draft
        $this->actingAs($admin)
             ->patch(route('admin.torneos.status', $t), ['status' => 'draft'])
             ->assertSessionHasErrors('status');
        $this->assertEquals('open', $t->fresh()->status);

        // Salto inválido: open → finished (se salta in_progress)
        $this->actingAs($admin)
             ->patch(route('admin.torneos.status', $t), ['status' => 'finished'])
             ->assertSessionHasErrors('status');
        $this->assertEquals('open', $t->fresh()->status);
    }

    public function test_usuario_sin_rol_torneo_admin_no_accede(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ]);

        $this->actingAs($user)
             ->get(route('admin.torneos.index'))
             ->assertRedirect();

        $this->actingAs($user)
             ->get(route('admin.torneos.create'))
             ->assertRedirect();
    }

    public function test_formularios_create_y_edit_renderizan_para_el_dueno(): void
    {
        $admin = $this->torneoAdmin();
        $t = $this->makeTournamentFor($admin);

        $this->actingAs($admin)->get(route('admin.torneos.create'))->assertOk()->assertSee('Nuevo torneo');
        $this->actingAs($admin)->get(route('admin.torneos.edit', $t))->assertOk();
    }

    public function test_dashboard_renderiza_para_el_dueno(): void
    {
        $admin = $this->torneoAdmin();
        $t = $this->makeTournamentFor($admin, ['name' => 'Mi Dashboard']);

        $this->actingAs($admin)
             ->get(route('admin.torneos.show', $t))
             ->assertOk()
             ->assertSee('Mi Dashboard')
             ->assertSee('Equipos inscritos');
    }

    public function test_torneo_admin_no_puede_ver_dashboard_de_torneo_ajeno(): void
    {
        $adminA = $this->torneoAdmin();
        $adminB = $this->torneoAdmin();
        $t = $this->makeTournamentFor($adminA);

        $this->actingAs($adminB)
             ->get(route('admin.torneos.show', $t))
             ->assertForbidden();
    }

    // ─── Prompt 6B: configuración avanzada ────────────────────────────────────

    public function test_los_campos_avanzados_se_guardan_correctamente(): void
    {
        $admin = $this->torneoAdmin();

        $this->actingAs($admin)->post(route('admin.torneos.store'), $this->validPayload([
            'name'                 => 'Copa Avanzada',
            'visibility'           => 'private',
            'category'             => 'veteranos',
            'city'                 => 'Bogotá',
            'venue'                => 'Cancha Central',
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'knockout_tiebreak'    => 'extra_time_penalties',
            'min_players_per_team' => 5,
            'max_players_per_team' => 18,
            'match_duration'       => 80,
            'max_substitutions'    => 7,
            'registration_fee'     => 50000,
            'prize_description'    => 'Trofeo + premio en efectivo',
        ]));

        $t = Tournament::where('name', 'Copa Avanzada')->firstOrFail();

        $this->assertEquals('private', $t->visibility);
        $this->assertEquals('veteranos', $t->category);
        $this->assertEquals('Bogotá', $t->city);
        $this->assertEquals('extra_time_penalties', $t->knockout_tiebreak);
        $this->assertEquals(80, $t->match_duration);
        $this->assertEquals(7, $t->max_substitutions);
        $this->assertEquals(50000, $t->registration_fee);
    }

    public function test_max_teams_se_calcula_y_valida_contra_groups_count(): void
    {
        $admin = $this->torneoAdmin();

        // Se calcula automáticamente: 2 * 4 = 8
        $this->actingAs($admin)->post(route('admin.torneos.store'), $this->validPayload([
            'name'            => 'Copa Calculada',
            'groups_count'    => 2,
            'teams_per_group' => 4,
        ]));
        $this->assertEquals(8, Tournament::where('name', 'Copa Calculada')->value('max_teams'));

        // max_teams inconsistente con grupos × equipos → error de validación
        $this->actingAs($admin)->post(route('admin.torneos.store'), $this->validPayload([
            'name'            => 'Copa Inconsistente',
            'groups_count'    => 2,
            'teams_per_group' => 4,
            'max_teams'       => 99,
        ]))->assertSessionHasErrors('max_teams');

        $this->assertDatabaseMissing('tournaments', ['name' => 'Copa Inconsistente']);
    }

    public function test_validacion_de_puntos_respeta_win_mayor_draw_mayor_igual_loss(): void
    {
        $admin = $this->torneoAdmin();

        // win no mayor que draw
        $this->actingAs($admin)->post(route('admin.torneos.store'), $this->validPayload([
            'name'        => 'Puntos Malos 1',
            'points_win'  => 1,
            'points_draw' => 1,
            'points_loss' => 0,
        ]))->assertSessionHasErrors('points_win');

        // draw menor que loss
        $this->actingAs($admin)->post(route('admin.torneos.store'), $this->validPayload([
            'name'        => 'Puntos Malos 2',
            'points_win'  => 3,
            'points_draw' => 0,
            'points_loss' => 1,
        ]))->assertSessionHasErrors('points_draw');

        $this->assertDatabaseMissing('tournaments', ['name' => 'Puntos Malos 1']);
        $this->assertDatabaseMissing('tournaments', ['name' => 'Puntos Malos 2']);
    }

    public function test_torneo_privado_no_aparece_en_listados_publicos(): void
    {
        $admin = $this->torneoAdmin();
        $publico = $this->makeTournamentFor($admin, ['name' => 'Público Visible', 'visibility' => 'public']);
        $privado = $this->makeTournamentFor($admin, ['name' => 'Privado Oculto', 'visibility' => 'private']);

        $publicIds = Tournament::public()->pluck('id')->all();

        $this->assertContains($publico->id, $publicIds);
        $this->assertNotContains($privado->id, $publicIds);
    }

    public function test_tiebreaker_order_se_guarda_y_recupera_como_array(): void
    {
        $admin = $this->torneoAdmin();

        $order = ['goals_for', 'goal_difference', 'head_to_head'];

        $this->actingAs($admin)->post(route('admin.torneos.store'), $this->validPayload([
            'name'             => 'Copa Tiebreak',
            'tiebreaker_order' => $order,
        ]));

        $t = Tournament::where('name', 'Copa Tiebreak')->firstOrFail();

        $this->assertIsArray($t->tiebreaker_order);
        $this->assertEquals($order, $t->tiebreaker_order);
    }
}
