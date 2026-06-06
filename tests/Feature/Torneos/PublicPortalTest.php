<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\GroupTeam;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentGroup;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sesión E — Portal público, exportación PDF/CSV y tarjetas compartibles.
 */
class PublicPortalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un torneo con fase de grupos, 2 equipos, posiciones, 2 partidos
     * jugados, 1 programado (con cancha) y un goleador con datos sensibles.
     *
     * @return array{tournament:Tournament,sensitivePlayer:User}
     */
    private function buildTournament(string $visibility = 'public'): array
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);

        $tournament = Tournament::create([
            'name' => 'Copa Pública Test', 'slug' => 'copa-publica-' . uniqid(),
            'sport' => 'futbol', 'status' => 'in_progress', 'format' => 'round_robin',
            'visibility' => $visibility, 'category' => 'libre', 'city' => 'Bucaramanga',
            'groups_count' => 1, 'teams_per_group' => 2, 'classifies_per_group' => 1,
            'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'mvp_enabled' => true, 'created_by_user_id' => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $phase = TournamentPhase::create([
            'tournament_id' => $tournament->id, 'name' => 'Fase de Grupos',
            'type' => 'groups', 'order' => 1, 'is_active' => true, 'status' => 'active',
        ]);
        $group = TournamentGroup::create(['phase_id' => $phase->id, 'name' => 'Grupo A', 'order' => 1]);

        // Equipos con nombres reconocibles.
        $leones = Team::create(['tournament_id' => $tournament->id, 'captain_user_id' => $admin->id, 'name' => 'Leones FC', 'status' => 'approved']);
        $tigres = Team::create(['tournament_id' => $tournament->id, 'captain_user_id' => $admin->id, 'name' => 'Tigres United', 'status' => 'approved']);
        foreach ([$leones, $tigres] as $t) {
            GroupTeam::create(['group_id' => $group->id, 'team_id' => $t->id]);
        }

        // Posiciones.
        Standing::create(['phase_id' => $phase->id, 'group_id' => $group->id, 'team_id' => $leones->id, 'played' => 2, 'won' => 2, 'drawn' => 0, 'lost' => 0, 'goals_for' => 5, 'goals_against' => 1, 'goal_difference' => 4, 'points' => 6, 'position' => 1]);
        Standing::create(['phase_id' => $phase->id, 'group_id' => $group->id, 'team_id' => $tigres->id, 'played' => 2, 'won' => 0, 'drawn' => 0, 'lost' => 2, 'goals_for' => 1, 'goals_against' => 5, 'goal_difference' => -4, 'points' => 0, 'position' => 2]);

        // Partidos jugados.
        TournamentMatch::create(['phase_id' => $phase->id, 'group_id' => $group->id, 'home_team_id' => $leones->id, 'away_team_id' => $tigres->id, 'home_score' => 3, 'away_score' => 1, 'winner_team_id' => $leones->id, 'status' => 'finished', 'match_number' => 1, 'scheduled_at' => now()->subDays(7)]);
        TournamentMatch::create(['phase_id' => $phase->id, 'group_id' => $group->id, 'home_team_id' => $tigres->id, 'away_team_id' => $leones->id, 'home_score' => 0, 'away_score' => 2, 'winner_team_id' => $leones->id, 'status' => 'finished', 'match_number' => 2, 'scheduled_at' => now()->subDays(3)]);

        // Próximo partido con cancha.
        TournamentMatch::create(['phase_id' => $phase->id, 'group_id' => $group->id, 'home_team_id' => $leones->id, 'away_team_id' => $tigres->id, 'status' => 'scheduled', 'match_number' => 3, 'scheduled_at' => now()->addDays(2), 'venue' => 'Cancha La Flora']);

        // Goleador con DATOS SENSIBLES (no deben filtrarse al portal).
        $sensitive = User::factory()->create([
            'is_active' => true, 'modules' => 'torneos', 'name' => 'Goleador Estrella',
            'email' => 'secreto@privado.test', 'phone_whatsapp' => '3001234567', 'document' => 'CC-998877',
        ]);
        $tp = TeamPlayer::create(['team_id' => $leones->id, 'user_id' => $sensitive->id, 'is_captain' => false, 'status' => 'active']);
        PlayerStat::create(['tournament_id' => $tournament->id, 'team_player_id' => $tp->id, 'goals' => 5, 'assists' => 2, 'matches_played' => 2, 'mvps' => 1]);

        return ['tournament' => $tournament, 'sensitivePlayer' => $sensitive];
    }

    // ── Acceso público / privacidad ───────────────────────────────────────

    public function test_portal_de_torneo_publico_responde_sin_login(): void
    {
        ['tournament' => $t] = $this->buildTournament('public');

        $res = $this->get(route('torneos.public.show', $t));   // sin actingAs

        $res->assertOk();
        $res->assertSee('Copa Pública Test');
    }

    public function test_portal_de_torneo_privado_no_es_accesible(): void
    {
        ['tournament' => $t] = $this->buildTournament('private');

        $this->get(route('torneos.public.show', $t))->assertNotFound();
    }

    public function test_el_portal_no_expone_datos_sensibles_de_usuarios(): void
    {
        ['tournament' => $t, 'sensitivePlayer' => $u] = $this->buildTournament('public');

        $res = $this->get(route('torneos.public.show', $t));

        $res->assertOk();
        $res->assertSee('Goleador Estrella');          // el nombre sí se muestra
        $res->assertDontSee('secreto@privado.test');   // email NO
        $res->assertDontSee('3001234567');             // teléfono NO
        $res->assertDontSee('CC-998877');              // documento NO
    }

    public function test_el_portal_muestra_tabla_resultados_y_proximos(): void
    {
        ['tournament' => $t] = $this->buildTournament('public');

        $res = $this->get(route('torneos.public.show', $t));

        $res->assertOk();
        $res->assertSee('Grupo A');
        $res->assertSee('Leones FC');
        $res->assertSee('Tigres United');
        $res->assertSee('3-1');                 // resultado
        $res->assertSee('Cancha La Flora');     // cancha del próximo
    }

    // ── Exportación ─────────────────────────────────────────────────────

    public function test_exportacion_pdf_genera_un_archivo_valido(): void
    {
        ['tournament' => $t] = $this->buildTournament('public');

        $res = $this->get(route('torneos.public.export', [$t, 'posiciones', 'pdf']));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_exportacion_csv_tiene_bom_utf8_y_datos(): void
    {
        ['tournament' => $t] = $this->buildTournament('public');

        $res = $this->get(route('torneos.public.export', [$t, 'resultados', 'csv']));

        $res->assertOk();
        $content = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);   // BOM UTF-8
        $this->assertStringContainsString('Marcador', $content);   // cabecera
        $this->assertStringContainsString('Leones FC', $content);  // dato
    }

    public function test_export_publico_bloqueado_para_torneo_privado(): void
    {
        ['tournament' => $t] = $this->buildTournament('private');

        $this->get(route('torneos.public.export', [$t, 'posiciones', 'csv']))->assertNotFound();
    }

    public function test_export_admin_requiere_gestionar_el_torneo(): void
    {
        ['tournament' => $t] = $this->buildTournament('private');
        $intruso = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);

        $this->actingAs($intruso)
            ->get(route('admin.torneos.export', [$t, 'resultados', 'pdf']))
            ->assertForbidden();
    }

    // ── Tarjeta compartible (SVG) ───────────────────────────────────────

    public function test_la_tarjeta_compartible_produce_una_imagen_svg_valida(): void
    {
        ['tournament' => $t] = $this->buildTournament('public');

        $res = $this->get(route('torneos.public.img', [$t, 'goleadores']));

        $res->assertOk();
        $this->assertStringContainsString('image/svg+xml', $res->headers->get('content-type'));
        $body = $res->getContent();
        $this->assertStringContainsString('<svg', $body);
        $this->assertStringContainsString('FUTGO', $body);
        $this->assertStringContainsString('Goleador Estrella', $body);
    }

    public function test_tarjeta_compartible_no_disponible_en_torneo_privado(): void
    {
        ['tournament' => $t] = $this->buildTournament('private');

        $this->get(route('torneos.public.img', [$t, 'goleadores']))->assertNotFound();
    }

    // ── Rendimiento: sin N+1 ────────────────────────────────────────────

    public function test_el_portal_no_tiene_consultas_n_mas_1(): void
    {
        ['tournament' => $t] = $this->buildTournament('public');

        DB::enableQueryLog();
        $this->get(route('torneos.public.show', $t))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Con eager loading el número de consultas es acotado y constante.
        $this->assertLessThan(25, $count, "El portal ejecutó {$count} consultas (posible N+1).");
    }

    // ── H9: portal de exploración de torneos públicos ────────────────────

    public function test_listado_publico_de_torneos_responde_sin_login(): void
    {
        // Un torneo abierto público + uno en juego público.
        ['tournament' => $inProgress] = $this->buildTournament('public');

        $admin = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
        $open = Tournament::create([
            'name' => 'Copa Abierta Inscripcion', 'slug' => 'copa-abierta-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'visibility' => 'public', 'category' => 'libre',
            'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1,
            'max_teams' => 4, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);

        $res = $this->get(route('torneos.public.index'));   // sin login

        $res->assertOk();
        $res->assertSee('Copa Abierta Inscripcion');     // sección abiertas
        $res->assertSee('Copa Pública Test');            // sección en juego
        $res->assertSee('Inscripciones abiertas');
        $res->assertSee('En juego');
    }

    public function test_listado_publico_no_muestra_torneos_privados(): void
    {
        ['tournament' => $privateT] = $this->buildTournament('private');

        $this->get(route('torneos.public.index'))
            ->assertOk()
            ->assertDontSee('Copa Pública Test');
    }

    public function test_listado_publico_ignora_borradores_y_finalizados(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);

        foreach (['draft' => 'Copa Borrador', 'finished' => 'Copa Finalizada'] as $status => $name) {
            Tournament::create([
                'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
                'sport' => 'futbol', 'status' => $status, 'format' => 'round_robin',
                'visibility' => 'public', 'category' => 'libre',
                'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1,
                'max_teams' => 4, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
                'created_by_user_id' => $admin->id,
            ]);
        }

        $res = $this->get(route('torneos.public.index'));
        $res->assertOk();
        $res->assertDontSee('Copa Borrador');
        $res->assertDontSee('Copa Finalizada');
    }

    public function test_capitan_ve_boton_inscribir_su_equipo_en_torneo_abierto(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
        $open = Tournament::create([
            'name' => 'Copa Para Inscribir', 'slug' => 'copa-inscribir-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'visibility' => 'public', 'category' => 'libre',
            'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1,
            'max_teams' => 4, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);

        // Capitán con un club permanente.
        $captain = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);
        \App\Models\Torneos\Club::create([
            'name' => 'Mi Club', 'slug' => 'mi-club-' . uniqid(),
            'captain_user_id' => $captain->id, 'created_by_user_id' => $captain->id,
        ]);

        $this->actingAs($captain)
            ->get(route('torneos.public.index'))
            ->assertOk()
            ->assertSee('Inscribir mi equipo');
    }

    public function test_inscripcion_deja_equipo_pendiente_de_aprobacion_admin(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
        $tournament = Tournament::create([
            'name' => 'Copa Aprobacion', 'slug' => 'copa-aprobacion-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'visibility' => 'public', 'category' => 'libre',
            'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1,
            'max_teams' => 4, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        // Capitán con club permanente.
        $captain = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);
        $club = \App\Models\Torneos\Club::create([
            'name' => 'Club Inscriptor', 'slug' => 'club-inscriptor-' . uniqid(),
            'captain_user_id' => $captain->id, 'created_by_user_id' => $captain->id,
        ]);
        \App\Models\Torneos\ClubPlayer::create([
            'club_id' => $club->id, 'user_id' => $captain->id, 'is_captain' => true,
            'verification_status' => 'registrado', 'status' => 'active',
        ]);

        // El capitán inscribe su equipo.
        $this->actingAs($captain)
            ->post(route('torneos.equipo.store', $tournament), ['club_id' => $club->id])
            ->assertRedirect();

        // El equipo queda pendiente de aprobación.
        $this->assertDatabaseHas('teams', [
            'tournament_id' => $tournament->id,
            'club_id'       => $club->id,
            'status'        => 'pending',
        ]);

        // El admin lo aprueba.
        $team = Team::where('tournament_id', $tournament->id)->where('club_id', $club->id)->first();
        $this->actingAs($admin)
            ->patch(route('admin.torneos.equipos.approve', [$tournament, $team]))
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'status' => 'approved']);
    }

    // ── E7/H10: ficha completa del torneo en "Ver detalle" ───────────────

    public function test_ver_detalle_muestra_ficha_completa_e_inscripcion(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
        $tournament = Tournament::create([
            'name' => 'Copa Ficha Completa', 'slug' => 'copa-ficha-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'visibility' => 'public', 'category' => 'veteranos', 'city' => 'Girón', 'venue' => 'Cancha La 30',
            'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1, 'max_teams' => 4,
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'match_duration' => 80, 'min_players_per_team' => 7, 'max_players_per_team' => 18,
            'registration_fee' => 80000, 'prize_description' => 'Trofeo + 1.000.000',
            'rules' => 'Reglamento oficial FutGO.', 'created_by_user_id' => $admin->id,
        ]);

        $res = $this->get(route('torneos.public.show', $tournament));   // sin login

        $res->assertOk();
        $res->assertSee('Información del torneo');
        $res->assertSee('$80.000');                  // costo en divisa
        $res->assertSee('Cancha La 30');             // sede
        $res->assertSee('Todos contra todos');       // formato traducido
        $res->assertSee('Trofeo + 1.000.000');       // premio
        $res->assertSee('Reglamento oficial FutGO.'); // reglamento
        $res->assertSee('Ingresá para inscribir tu equipo'); // CTA inscripción (guest)
    }

    public function test_admin_de_plataforma_ve_torneos_privados_en_buscar(): void
    {
        // Un torneo privado en juego.
        ['tournament' => $privateT] = $this->buildTournament('private');

        $platformAdmin = User::factory()->create(['is_active' => true, 'role' => 'admin', 'modules' => 'full']);

        // El admin de plataforma SÍ lo ve en el listado…
        $this->actingAs($platformAdmin)
            ->get(route('torneos.public.index'))
            ->assertOk()
            ->assertSee('Copa Pública Test')
            ->assertSee('Privado');

        // …y puede abrir su detalle (que para el público sería 404).
        $this->actingAs($platformAdmin)
            ->get(route('torneos.public.show', $privateT))
            ->assertOk();
    }

    public function test_usuario_normal_no_ve_privados_ni_su_detalle(): void
    {
        ['tournament' => $privateT] = $this->buildTournament('private');
        $user = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);

        $this->actingAs($user)
            ->get(route('torneos.public.index'))
            ->assertOk()
            ->assertDontSee('Copa Pública Test');

        $this->actingAs($user)
            ->get(route('torneos.public.show', $privateT))
            ->assertNotFound();
    }

    public function test_capitan_ve_boton_inscribir_en_ver_detalle(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
        $tournament = Tournament::create([
            'name' => 'Copa Detalle Cap', 'slug' => 'copa-detalle-cap-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'visibility' => 'public', 'category' => 'libre',
            'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1, 'max_teams' => 4,
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'created_by_user_id' => $admin->id,
        ]);

        $captain = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);
        \App\Models\Torneos\Club::create([
            'name' => 'Club Detalle', 'slug' => 'club-detalle-' . uniqid(),
            'captain_user_id' => $captain->id, 'created_by_user_id' => $captain->id,
        ]);

        $this->actingAs($captain)
            ->get(route('torneos.public.show', $tournament))
            ->assertOk()
            ->assertSee('Inscribir mi equipo');
    }
}
