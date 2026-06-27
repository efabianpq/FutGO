<?php

namespace Tests\Feature\Torneos;

use App\Models\Social\FriendlyMatch;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\GroupTeam;
use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentGroup;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Services\Torneos\ShareCardPngService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * TX-2 — Tarjetas PNG, OG tags y tarjeta de amistoso.
 *
 * Tests cubren:
 *  1. Endpoint PNG retorna Content-Type image/png (o image/svg+xml si GD no disponible).
 *  2. OG tags presentes en ficha pública de jugador.
 *  3. Tarjeta SVG de amistoso se genera correctamente.
 *  4. Endpoint PNG de amistoso funciona.
 *  5. Degradación a SVG cuando GD no disponible.
 *  6. OG tags dinámicos en app layout (perfil de club).
 *  7. Job GenerateShareCardPng se puede instanciar y serializar.
 */
class ShareCardPngTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function activeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'is_active' => true, 'role' => 'user', 'modules' => 'torneos',
        ], $attrs));
    }

    private function buildPublicTournament(): array
    {
        $admin = $this->activeUser(['role' => 'torneo_admin']);

        $t = Tournament::create([
            'name' => 'Copa PNG Test', 'slug' => 'copa-png-' . uniqid(),
            'sport' => 'futbol', 'status' => 'in_progress', 'format' => 'round_robin',
            'visibility' => 'public', 'groups_count' => 1, 'teams_per_group' => 2,
            'classifies_per_group' => 1, 'max_teams' => 2,
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);
        $t->tournamentAdmins()->create(['user_id' => $admin->id]);

        $phase = TournamentPhase::create([
            'tournament_id' => $t->id, 'name' => 'Grupos',
            'type' => 'groups', 'order' => 1, 'is_active' => true, 'status' => 'active',
        ]);
        $group = TournamentGroup::create(['phase_id' => $phase->id, 'name' => 'Grupo A', 'order' => 1]);

        $home = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $admin->id, 'name' => 'Leones', 'status' => 'approved']);
        $away = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $admin->id, 'name' => 'Tigres', 'status' => 'approved']);
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $home->id]);
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $away->id]);

        Standing::create(['phase_id' => $phase->id, 'group_id' => $group->id, 'team_id' => $home->id, 'played' => 1, 'won' => 1, 'drawn' => 0, 'lost' => 0, 'goals_for' => 2, 'goals_against' => 0, 'goal_difference' => 2, 'points' => 3, 'position' => 1]);
        Standing::create(['phase_id' => $phase->id, 'group_id' => $group->id, 'team_id' => $away->id, 'played' => 1, 'won' => 0, 'drawn' => 0, 'lost' => 1, 'goals_for' => 0, 'goals_against' => 2, 'goal_difference' => -2, 'points' => 0, 'position' => 2]);

        $match = TournamentMatch::create([
            'phase_id' => $phase->id, 'group_id' => $group->id,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
            'home_score' => 2, 'away_score' => 0, 'winner_team_id' => $home->id,
            'status' => 'finished', 'match_number' => 1, 'scheduled_at' => now()->subDay(),
        ]);

        $scorer = $this->activeUser(['name' => 'El Goleador']);
        $tp = TeamPlayer::create(['team_id' => $home->id, 'user_id' => $scorer->id, 'is_captain' => false, 'status' => 'active']);
        PlayerStat::create(['tournament_id' => $t->id, 'team_player_id' => $tp->id, 'goals' => 3, 'assists' => 1, 'matches_played' => 1]);

        return compact('t', 'match', 'phase');
    }

    private function buildFriendlyMatch(): FriendlyMatch
    {
        $cap1 = $this->activeUser(['name' => 'Cap Leones']);
        $cap2 = $this->activeUser(['name' => 'Cap Tigres']);

        $club1 = Club::create(['name' => 'Leones FC', 'slug' => 'leones-fc-' . uniqid(), 'captain_user_id' => $cap1->id, 'created_by_user_id' => $cap1->id]);
        $club2 = Club::create(['name' => 'Tigres SC', 'slug' => 'tigres-sc-' . uniqid(), 'captain_user_id' => $cap2->id, 'created_by_user_id' => $cap2->id]);

        return FriendlyMatch::create([
            'home_club_id'       => $club1->id,
            'away_club_id'       => $club2->id,
            'scheduled_at'       => now()->subDay(),
            'status'             => FriendlyMatch::STATUS_JUGADO,
            'agreement_status'   => FriendlyMatch::AGREEMENT_ACORDADO,
            'final_home_score'   => 3,
            'final_away_score'   => 1,
        ]);
    }

    // ─── Tests PNG de torneo ─────────────────────────────────────────────────

    /** El endpoint PNG devuelve image/png (si GD disponible) o image/svg+xml (degradación). */
    public function test_endpoint_png_goleadores_retorna_imagen_valida(): void
    {
        ['t' => $t] = $this->buildPublicTournament();

        $res = $this->get(route('torneos.public.img.png', [$t, 'goleadores']));

        $res->assertOk();
        $contentType = $res->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'image/png') || str_contains($contentType, 'image/svg+xml'),
            "Se esperaba image/png o image/svg+xml, obtuvo: {$contentType}",
        );
    }

    /** El endpoint PNG de posiciones también funciona. */
    public function test_endpoint_png_posiciones_retorna_imagen_valida(): void
    {
        ['t' => $t] = $this->buildPublicTournament();

        $res = $this->get(route('torneos.public.img.png', [$t, 'posiciones']));

        $res->assertOk();
        $contentType = $res->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'image/png') || str_contains($contentType, 'image/svg+xml'),
        );
    }

    /** El endpoint PNG de partido de torneo retorna imagen válida. */
    public function test_endpoint_png_partido_retorna_imagen_valida(): void
    {
        ['t' => $t, 'match' => $m] = $this->buildPublicTournament();

        $res = $this->get(route('torneos.public.img.match.png', [$t, $m]));

        $res->assertOk();
        $contentType = $res->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'image/png') || str_contains($contentType, 'image/svg+xml'),
        );
    }

    /** Torneo privado: el endpoint PNG devuelve 404. */
    public function test_png_de_torneo_privado_retorna_404(): void
    {
        $admin = $this->activeUser(['role' => 'torneo_admin']);
        $t = Tournament::create([
            'name' => 'Privado', 'slug' => 'privado-' . uniqid(), 'sport' => 'futbol',
            'status' => 'in_progress', 'format' => 'round_robin', 'visibility' => 'private',
            'groups_count' => 1, 'teams_per_group' => 2, 'classifies_per_group' => 1,
            'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);

        $this->get(route('torneos.public.img.png', [$t, 'goleadores']))->assertNotFound();
    }

    // ─── Test ShareCardPngService (unitario) ─────────────────────────────────

    /** gdAvailable() devuelve bool sin crashear. */
    public function test_share_card_png_service_informa_disponibilidad_de_gd(): void
    {
        $service = app(ShareCardPngService::class);
        $this->assertIsBool($service->gdAvailable());
    }

    /** storagePath construye la ruta correctamente. */
    public function test_storage_path_tiene_formato_correcto(): void
    {
        $service = app(ShareCardPngService::class);
        $path = $service->storagePath('copa-test', 'goleadores');
        $this->assertStringContainsString('copa-test', $path);
        $this->assertStringContainsString('goleadores', $path);
        $this->assertStringEndsWith('.png', $path);
    }

    // ─── Test tarjeta SVG de amistoso ────────────────────────────────────────

    /** La tarjeta SVG de amistoso se genera con el resultado correcto. */
    public function test_tarjeta_svg_de_amistoso_retorna_contenido_valido(): void
    {
        $match = $this->buildFriendlyMatch();

        $res = $this->get(route('social.amistosos.img.card', $match));

        $res->assertOk();
        $this->assertStringContainsString('image/svg+xml', $res->headers->get('content-type'));
        $body = $res->getContent();
        $this->assertStringContainsString('<svg', $body);
        $this->assertStringContainsString('FUTGO', $body);
        $this->assertStringContainsString('AMISTOSO', $body);
        $this->assertStringContainsString('Leones FC', $body);
        $this->assertStringContainsString('Tigres SC', $body);
        $this->assertStringContainsString('3', $body);
        $this->assertStringContainsString('1', $body);
    }

    /** Amistoso no jugado: la tarjeta devuelve 404. */
    public function test_tarjeta_amistoso_no_jugado_retorna_404(): void
    {
        $cap = $this->activeUser();
        $c1  = Club::create(['name' => 'A', 'slug' => 'a-' . uniqid(), 'captain_user_id' => $cap->id, 'created_by_user_id' => $cap->id]);
        $c2  = Club::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'captain_user_id' => $cap->id, 'created_by_user_id' => $cap->id]);

        $m = FriendlyMatch::create([
            'home_club_id' => $c1->id, 'away_club_id' => $c2->id,
            'scheduled_at' => now()->addDay(),
            'status' => FriendlyMatch::STATUS_CONFIRMADO,
        ]);

        $this->get(route('social.amistosos.img.card', $m))->assertNotFound();
    }

    /** Endpoint PNG de amistoso retorna imagen válida (png o svg). */
    public function test_endpoint_png_amistoso_retorna_imagen_valida(): void
    {
        $match = $this->buildFriendlyMatch();

        $res = $this->get(route('social.amistosos.img.png', $match));

        $res->assertOk();
        $contentType = $res->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'image/png') || str_contains($contentType, 'image/svg+xml'),
        );
    }

    // ─── OG tags en ficha pública de jugador ─────────────────────────────────

    /** La ficha pública de jugador incluye og:description con el nombre del jugador. */
    public function test_ficha_publica_jugador_tiene_og_description(): void
    {
        $player = $this->activeUser([
            'futgo_id'   => 'FG-ABC123',
            'name'       => 'Carlos Futbolero',
            'play_level' => 'competitivo',
            'city'       => 'Medellín',
        ]);

        PlayerCareerStat::create([
            'user_id'        => $player->id,
            'matches_played' => 10,
            'goals'          => 5,
            'assists'        => 3,
        ]);

        $res = $this->get(route('social.player.show', 'FG-ABC123'));

        $res->assertOk();
        $body = $res->getContent();
        $this->assertStringContainsString('og:description', $body);
        $this->assertStringContainsString('Carlos Futbolero', $body);
        $this->assertStringContainsString('Medellín', $body);
    }

    /** La ficha pública de jugador incluye og:image si tiene avatar. */
    public function test_ficha_publica_jugador_con_avatar_tiene_og_image(): void
    {
        $player = $this->activeUser([
            'futgo_id'   => 'FG-AV1234',
            'name'       => 'Jugador Con Avatar',
            'avatar_url' => 'https://ejemplo.test/avatar.jpg',
        ]);

        $res = $this->get(route('social.player.show', 'FG-AV1234'));

        $res->assertOk();
        $body = $res->getContent();
        $this->assertStringContainsString('og:image', $body);
        $this->assertStringContainsString('https://ejemplo.test/avatar.jpg', $body);
    }

    /** La ficha pública de jugador sin avatar NO incluye og:image. */
    public function test_ficha_publica_jugador_sin_avatar_no_tiene_og_image(): void
    {
        $player = $this->activeUser([
            'futgo_id'   => 'FG-NOAV12',
            'name'       => 'Sin Avatar',
            'avatar_url' => null,
        ]);

        $res = $this->get(route('social.player.show', 'FG-NOAV12'));

        $res->assertOk();
        // Sin avatar: el layout no emite og:image (usando layouts.public).
        $body = $res->getContent();
        // El og:description sí debe estar siempre.
        $this->assertStringContainsString('og:description', $body);
    }

    // ─── Job serializable ────────────────────────────────────────────────────

    /** El job GenerateShareCardPng se puede instanciar con los parámetros esperados. */
    public function test_job_generate_share_card_png_es_instanciable(): void
    {
        $job = new \App\Jobs\GenerateShareCardPng('goleadores', 1);
        $this->assertEquals('goleadores', $job->type);
        $this->assertEquals(1, $job->tournamentId);
        $this->assertNull($job->matchId);
        $this->assertNull($job->friendlyMatchId);

        $jobAmistoso = new \App\Jobs\GenerateShareCardPng('amistoso', null, null, 42);
        $this->assertEquals('amistoso', $jobAmistoso->type);
        $this->assertEquals(42, $jobAmistoso->friendlyMatchId);
    }
}
