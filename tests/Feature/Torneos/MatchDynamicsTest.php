<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\RosterMovement;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sesión C — dinámica del partido: convocatoria previa, MVP y bajas/cambios.
 */
class MatchDynamicsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
    }

    private function makeUser(): User
    {
        return User::factory()->create(['is_active' => true, 'role' => 'user', 'modules' => 'torneos']);
    }

    /** Torneo round_robin con fixture; cada equipo tiene capitán + 1 jugador extra. */
    private function scenario(User $admin, int $teamCount = 4, bool $mvp = false): array
    {
        $tournament = Tournament::create([
            'name' => 'Copa ' . uniqid(), 'slug' => 'copa-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => $teamCount, 'classifies_per_group' => 1,
            'max_teams' => $teamCount, 'third_place_match' => false,
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'match_duration' => 90, 'mvp_enabled' => $mvp,
            'created_by_user_id' => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = [];
        for ($i = 0; $i < $teamCount; $i++) {
            $captain = $this->makeUser();
            $team = Team::create([
                'tournament_id' => $tournament->id, 'captain_user_id' => $captain->id,
                'name' => "Equipo $i", 'status' => 'approved',
            ]);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $captain->id, 'is_captain' => true, 'status' => 'active']);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'active']);
            $teams[] = $team;
        }

        app(FixtureGeneratorService::class)->generate($tournament);
        return [$tournament->fresh(), $teams];
    }

    private function firstMatch(Tournament $t): TournamentMatch
    {
        return TournamentMatch::whereHas('phase', fn ($q) => $q->where('tournament_id', $t->id))
            ->orderBy('match_number')->first();
    }

    // ─── Convocatoria previa ──────────────────────────────────────────────────

    public function test_capitan_arma_convocatoria_y_jugador_confirma(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin);
        $match   = $this->firstMatch($t);
        $home    = Team::find($match->home_team_id);
        $captain = User::find($home->captain_user_id);
        $players = $home->players()->get();

        // El capitán arma la convocatoria con ambos jugadores.
        $this->actingAs($captain)->post(route('torneos.convocatoria.store', [$t, $match]), [
            'player_ids' => $players->pluck('id')->all(),
        ])->assertRedirect();

        $this->assertEquals(2, MatchCallUp::where('match_id', $match->id)->where('team_id', $home->id)->count());

        // Un jugador confirma su asistencia.
        $player = $players->firstWhere('is_captain', false);
        $this->actingAs(User::find($player->user_id))
            ->post(route('torneos.convocatoria.respond', [$t, $match]), ['response' => 'confirmado'])
            ->assertRedirect();

        $this->assertDatabaseHas('match_call_ups', [
            'match_id' => $match->id, 'team_player_id' => $player->id, 'status' => 'confirmado',
        ]);
    }

    public function test_no_capitan_no_puede_armar_convocatoria(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin);
        $match = $this->firstMatch($t);
        $home  = Team::find($match->home_team_id);
        $raso  = User::find($home->players()->where('is_captain', false)->first()->user_id);

        $this->actingAs($raso)->post(route('torneos.convocatoria.store', [$t, $match]), [
            'player_ids' => [],
        ])->assertForbidden();
    }

    // ─── MVP ──────────────────────────────────────────────────────────────────

    public function test_mvp_habilitado_suma_al_jugador(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin, 4, true);
        $match  = $this->firstMatch($t);
        $home   = Team::find($match->home_team_id);
        $player = $home->players()->where('is_captain', true)->first();

        $this->actingAs($admin)->post(route('admin.torneos.partidos.store', [$t, $match]), [
            'home_score' => 1, 'away_score' => 0,
            'lineups' => [[
                'team_player_id' => $player->id, 'team_id' => $home->id,
                'started' => 1, 'minute_in' => 0, 'minute_out' => '',
            ]],
            'events' => [['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 10]],
            'mvp_team_player_id' => $player->id,
        ])->assertRedirect();

        $this->assertEquals($player->id, $match->fresh()->mvp_team_player_id);
        $stat = PlayerStat::where('tournament_id', $t->id)->where('team_player_id', $player->id)->first();
        $this->assertEquals(1, $stat->mvps);

        $career = PlayerCareerStat::where('user_id', $player->user_id)->first();
        $this->assertNotNull($career);
        $this->assertEquals(1, $career->mvps);
    }

    public function test_mvp_deshabilitado_no_se_asigna(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin, 4, false); // MVP off
        $match  = $this->firstMatch($t);
        $home   = Team::find($match->home_team_id);
        $player = $home->players()->where('is_captain', true)->first();

        // Aunque se envíe el campo, el backend lo ignora porque el torneo no usa MVP.
        $this->actingAs($admin)->post(route('admin.torneos.partidos.store', [$t, $match]), [
            'home_score' => 0, 'away_score' => 0,
            'lineups' => [[
                'team_player_id' => $player->id, 'team_id' => $home->id,
                'started' => 1, 'minute_in' => 0, 'minute_out' => '',
            ]],
            'mvp_team_player_id' => $player->id,
        ])->assertRedirect();

        $this->assertNull($match->fresh()->mvp_team_player_id);
        $stat = PlayerStat::where('tournament_id', $t->id)->where('team_player_id', $player->id)->first();
        $this->assertEquals(0, $stat->mvps);
    }

    public function test_formulario_no_muestra_mvp_si_esta_deshabilitado(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin, 4, false);
        $match = $this->firstMatch($t);

        $this->actingAs($admin)->get(route('admin.torneos.partidos.resultado', [$t, $match]))
            ->assertOk()
            ->assertDontSee('Figura del partido');
    }

    public function test_formulario_muestra_mvp_si_esta_habilitado(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin, 4, true);
        $match = $this->firstMatch($t);

        $this->actingAs($admin)->get(route('admin.torneos.partidos.resultado', [$t, $match]))
            ->assertOk()
            ->assertSee('Figura del partido');
    }

    // ─── Bajas y cambios ──────────────────────────────────────────────────────

    public function test_baja_conserva_estadisticas_previas(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin, 4, false);
        $match  = $this->firstMatch($t);
        $home   = Team::find($match->home_team_id);
        $player = $home->players()->where('is_captain', false)->first();

        // El jugador juega un partido (genera stats).
        $this->actingAs($admin)->post(route('admin.torneos.partidos.store', [$t, $match]), [
            'home_score' => 1, 'away_score' => 0,
            'lineups' => [[
                'team_player_id' => $player->id, 'team_id' => $home->id,
                'started' => 1, 'minute_in' => 0, 'minute_out' => '',
            ]],
            'events' => [['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 12]],
        ]);

        $t->update(['status' => 'in_progress']);

        // Baja durante el torneo.
        $this->actingAs($admin)->patch(route('admin.torneos.equipos.players.release', [$t, $home, $player]))
            ->assertRedirect();

        $this->assertEquals('inactive', $player->fresh()->status);
        // Las stats del partido jugado se conservan.
        $stat = PlayerStat::where('tournament_id', $t->id)->where('team_player_id', $player->id)->first();
        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->goals);
        // Queda registrado en el historial.
        $this->assertDatabaseHas('roster_movements', [
            'tournament_id' => $t->id, 'team_player_id' => $player->id, 'type' => 'baja',
        ]);
    }

    public function test_jugador_dado_de_baja_no_aparece_en_convocatoria(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin, 4, false);
        $match   = $this->firstMatch($t);
        $home    = Team::find($match->home_team_id);
        $captain = User::find($home->captain_user_id);
        $player  = $home->players()->where('is_captain', false)->first();
        $playerName = $player->displayName();

        $this->actingAs($admin)->patch(route('admin.torneos.equipos.players.release', [$t, $home, $player]))->assertRedirect();

        // La convocatoria se arma desde los jugadores activos: el dado de baja queda fuera.
        $this->actingAs($captain)->get(route('torneos.convocatoria.manage', [$t, $match]))->assertOk();
        $activeIds = $home->players()->where('status', 'active')->pluck('id');
        $this->assertFalse($activeIds->contains($player->id));
    }

    /** Torneo en inscripción (open) con 2 equipos, SIN fixture (no pasa a in_progress). */
    private function openTeams(User $admin): array
    {
        $t = Tournament::create([
            'name' => 'Copa ' . uniqid(), 'slug' => 'copa-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 2, 'classifies_per_group' => 1,
            'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'match_duration' => 90, 'created_by_user_id' => $admin->id,
        ]);
        $t->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = [];
        foreach (['A', 'B'] as $n) {
            $cap = $this->makeUser();
            $team = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $cap->id, 'name' => "Equipo $n", 'status' => 'approved']);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'is_captain' => true, 'status' => 'active']);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'active']);
            $teams[] = $team;
        }
        return [$t, $teams];
    }

    public function test_cambio_de_equipo_permitido_en_inscripcion(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->openTeams($admin); // status open, sin fixture
        $home   = $teams[0];
        $target = $teams[1];
        $player = $home->players()->where('is_captain', false)->first();

        $this->actingAs($admin)->patch(route('admin.torneos.equipos.players.transfer', [$t, $home, $player]), [
            'to_team_id' => $target->id,
        ])->assertRedirect();

        $this->assertEquals($target->id, $player->fresh()->team_id);
        $this->assertDatabaseHas('roster_movements', [
            'tournament_id' => $t->id, 'team_player_id' => $player->id, 'type' => 'cambio',
            'from_team_id' => $home->id, 'to_team_id' => $target->id,
        ]);
    }

    public function test_cambio_de_equipo_bloqueado_en_curso(): void
    {
        $admin = $this->makeAdmin();
        [$t, $teams] = $this->scenario($admin, 4, false);
        $t->update(['status' => 'in_progress']);

        $home   = $teams[0];
        $target = $teams[1];
        $player = $home->players()->where('is_captain', false)->first();

        $this->actingAs($admin)->patch(route('admin.torneos.equipos.players.transfer', [$t, $home, $player]), [
            'to_team_id' => $target->id,
        ])->assertRedirect();

        // No se movió.
        $this->assertEquals($home->id, $player->fresh()->team_id);
        $this->assertDatabaseMissing('roster_movements', [
            'tournament_id' => $t->id, 'team_player_id' => $player->id, 'type' => 'cambio',
        ]);
    }
}
