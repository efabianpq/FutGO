<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\StandingsCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentHubTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            [],
            $attrs
        ));
    }

    /**
     * Torneo round_robin con N equipos aprobados y fixture generado.
     * Cada equipo tiene su capitán como jugador activo.
     *
     * @return array{0:Tournament,1:\Illuminate\Support\Collection,2:User,3:\App\Models\Torneos\TournamentPhase}
     *         [tournament, teams, admin, phase]
     */
    private function makeScenario(int $n = 4): array
    {
        $admin = $this->makeUser(['role' => 'user']);

        $tournament = Tournament::create([
            'name'                 => 'Copa ' . uniqid(),
            'slug'                 => 'copa-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'round_robin',
            'groups_count'         => 1,
            'teams_per_group'      => $n,
            'classifies_per_group' => 1,
            'max_teams'            => $n,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'match_duration'       => 90,
            'category'             => 'libre',
            'visibility'           => 'public',
            'created_by_user_id'   => $admin->id,
        ]);

        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = collect();
        for ($i = 0; $i < $n; $i++) {
            $cap = $this->makeUser(['name' => "Capitan$i"]);
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $cap->id,
                'name'            => "Equipo$i",
                'status'          => 'approved',
            ]);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'status' => 'active']);
            $teams->push($team);
        }

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();
        $phase = $tournament->phases()->first();

        return [$tournament, $teams, $admin, $phase];
    }

    private function captainOf(Team $team): User
    {
        return User::find($team->captain_user_id);
    }

    private function finishFirstMatch(\App\Models\Torneos\TournamentPhase $phase): TournamentMatch
    {
        $match = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();
        $match->update([
            'home_score' => 2, 'away_score' => 0,
            'winner_team_id' => $match->home_team_id, 'status' => 'finished',
        ]);
        app(StandingsCalculatorService::class)->recalculate($phase);
        return $match;
    }

    // ─── Acceso ─────────────────────────────────────────────────────────────

    public function test_participante_jugador_puede_acceder(): void
    {
        [$tournament, $teams] = $this->makeScenario();
        $captain = $this->captainOf($teams[0]);

        $this->actingAs($captain)
            ->get(route('torneos.hub', $tournament))
            ->assertOk()
            ->assertSee('Centro de información');
    }

    public function test_capitan_puede_acceder(): void
    {
        [$tournament, $teams] = $this->makeScenario();
        $captain = $this->captainOf($teams[1]);

        $this->actingAs($captain)
            ->get(route('torneos.hub', $tournament))
            ->assertOk();
    }

    public function test_jugador_no_capitan_puede_acceder(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        // Agregar un jugador (no capitán) al equipo 0
        $player = $this->makeUser(['name' => 'Jugador Raso']);
        TeamPlayer::create(['team_id' => $teams[0]->id, 'user_id' => $player->id, 'status' => 'active']);

        $this->actingAs($player)
            ->get(route('torneos.hub', $tournament))
            ->assertOk();
    }

    public function test_administrador_del_torneo_puede_acceder(): void
    {
        [$tournament, $teams, $admin] = $this->makeScenario();

        $this->actingAs($admin)
            ->get(route('torneos.hub', $tournament))
            ->assertOk();
    }

    public function test_administrador_global_puede_acceder(): void
    {
        [$tournament] = $this->makeScenario();
        $globalAdmin = $this->makeUser(['role' => 'admin']);

        $this->actingAs($globalAdmin)
            ->get(route('torneos.hub', $tournament))
            ->assertOk();
    }

    public function test_usuario_ajeno_no_puede_acceder(): void
    {
        [$tournament] = $this->makeScenario();
        $outsider = $this->makeUser(['name' => 'Ajeno']);

        $this->actingAs($outsider)
            ->get(route('torneos.hub', $tournament))
            ->assertForbidden();
    }

    public function test_middleware_bloquea_usuarios_externos_con_403(): void
    {
        [$tournament] = $this->makeScenario();

        // Usuario que pertenece a OTRO torneo, no a este
        $otherAdmin = $this->makeUser(['role' => 'user']);
        $otherTournament = Tournament::create([
            'name' => 'Otra', 'slug' => 'otra-' . uniqid(), 'sport' => 'futbol',
            'status' => 'open', 'format' => 'round_robin', 'groups_count' => 1,
            'teams_per_group' => 2, 'classifies_per_group' => 1, 'max_teams' => 2,
            'third_place_match' => false, 'points_win' => 3, 'points_draw' => 1,
            'points_loss' => 0, 'match_duration' => 90, 'category' => 'libre',
            'visibility' => 'public', 'created_by_user_id' => $otherAdmin->id,
        ]);
        $cap = $this->makeUser();
        $foreignTeam = Team::create([
            'tournament_id' => $otherTournament->id, 'captain_user_id' => $cap->id,
            'name' => 'Foráneo', 'status' => 'approved',
        ]);
        TeamPlayer::create(['team_id' => $foreignTeam->id, 'user_id' => $cap->id, 'status' => 'active']);

        // El capitán del otro torneo no puede ver este hub
        $this->actingAs($cap)
            ->get(route('torneos.hub', $tournament))
            ->assertStatus(403);
    }

    public function test_usuario_no_autenticado_redirige_a_login(): void
    {
        [$tournament] = $this->makeScenario();

        $this->get(route('torneos.hub', $tournament))
            ->assertRedirect(route('login'));
    }

    // ─── Secciones ────────────────────────────────────────────────────────────

    public function test_se_muestran_proximos_partidos(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        $this->actingAs($this->captainOf($teams[0]))
            ->get(route('torneos.hub', $tournament))
            ->assertOk()
            ->assertSee('Próximos partidos')
            ->assertDontSee('Sin próximos partidos programados');
    }

    public function test_se_muestran_standings(): void
    {
        [$tournament, $teams, $admin, $phase] = $this->makeScenario();
        $this->finishFirstMatch($phase);

        $this->actingAs($this->captainOf($teams[0]))
            ->get(route('torneos.hub', $tournament))
            ->assertOk()
            ->assertSee('Tabla de posiciones')
            ->assertSee('PTS')
            ->assertDontSee('Pendiente de cálculo');
    }

    public function test_standings_pendiente_de_calculo_sin_resultados(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        $this->actingAs($this->captainOf($teams[0]))
            ->get(route('torneos.hub', $tournament))
            ->assertOk()
            ->assertSee('Pendiente de cálculo');
    }

    public function test_se_muestran_goleadores(): void
    {
        [$tournament, $teams, $admin, $phase] = $this->makeScenario();

        // Crear estadística de goleador para el capitán del equipo 0
        $captainPlayer = TeamPlayer::where('team_id', $teams[0]->id)->first();
        PlayerStat::create([
            'tournament_id'  => $tournament->id,
            'team_player_id' => $captainPlayer->id,
            'goals'          => 5,
            'assists'        => 2,
            'matches_played' => 3,
        ]);

        $this->actingAs($this->captainOf($teams[0]))
            ->get(route('torneos.hub', $tournament))
            ->assertOk()
            ->assertSee('Goleadores')
            ->assertSee('Capitan0')
            ->assertSee('5');
    }

    public function test_se_muestran_equipos_participantes(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        $this->actingAs($this->captainOf($teams[0]))
            ->get(route('torneos.hub', $tournament))
            ->assertOk()
            ->assertSee('Equipos participantes')
            ->assertSee($teams[0]->name)
            ->assertSee($teams[1]->name)
            ->assertSee('Capitan0'); // nombre del capitán
    }
}
