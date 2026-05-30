<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\GroupTeam;
use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentAdmin;
use App\Models\Torneos\TournamentGroup;
use App\Models\Torneos\TournamentInvitation;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'       => 'Test User',
            'email'      => uniqid('user') . '@test.com',
            'password'   => bcrypt('password'),
            'is_active'  => true,
            'role'       => 'user',
            'modules'    => 'torneos',
        ], $extra));
    }

    private function makeTournament(User $creator): Tournament
    {
        return Tournament::create([
            'name'               => 'Copa Test',
            'slug'               => uniqid('copa-'),
            'sport'              => 'futbol',
            'status'             => 'draft',
            'format'             => 'groups_and_knockout',
            'groups_count'       => 2,
            'teams_per_group'    => 4,
            'classifies_per_group' => 2,
            'third_place_match'  => false,
            'created_by_user_id' => $creator->id,
        ]);
    }

    private function makeTeam(Tournament $tournament, User $captain): Team
    {
        return Team::create([
            'tournament_id'    => $tournament->id,
            'captain_user_id'  => $captain->id,
            'name'             => uniqid('Equipo '),
            'status'           => 'approved',
        ]);
    }

    private function makePhase(Tournament $tournament): TournamentPhase
    {
        return TournamentPhase::create([
            'tournament_id' => $tournament->id,
            'name'          => 'Fase de Grupos',
            'type'          => 'groups',
            'order'         => 1,
            'is_active'     => true,
        ]);
    }

    // --- Tests de creación en BD ---

    public function test_tournament_se_puede_crear_en_bd(): void
    {
        $user       = $this->makeUser();
        $tournament = $this->makeTournament($user);

        $this->assertDatabaseHas('tournaments', ['id' => $tournament->id, 'slug' => $tournament->slug]);
    }

    public function test_team_se_puede_crear_en_bd(): void
    {
        $user    = $this->makeUser();
        $torneo  = $this->makeTournament($user);
        $team    = $this->makeTeam($torneo, $user);

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'tournament_id' => $torneo->id]);
    }

    public function test_team_player_se_puede_crear_en_bd(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $team   = $this->makeTeam($torneo, $user);

        $player = TeamPlayer::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        $this->assertDatabaseHas('team_players', ['id' => $player->id, 'team_id' => $team->id]);
    }

    public function test_tournament_match_se_puede_crear_en_bd(): void
    {
        $user    = $this->makeUser();
        $torneo  = $this->makeTournament($user);
        $phase   = $this->makePhase($torneo);
        $home    = $this->makeTeam($torneo, $user);
        $away    = $this->makeTeam($torneo, $this->makeUser());

        $match = TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status'       => 'scheduled',
            'match_number' => 1,
        ]);

        $this->assertDatabaseHas('tournament_matches', ['id' => $match->id]);
    }

    public function test_standing_se_puede_crear_en_bd(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $phase  = $this->makePhase($torneo);
        $team   = $this->makeTeam($torneo, $user);

        $standing = Standing::create([
            'phase_id' => $phase->id,
            'team_id'  => $team->id,
            'played'   => 0,
            'won'      => 0,
            'drawn'    => 0,
            'lost'     => 0,
            'points'   => 0,
        ]);

        $this->assertDatabaseHas('standings', ['id' => $standing->id]);
    }

    // --- Tests de relaciones críticas ---

    public function test_tournament_relacion_creator_retorna_user(): void
    {
        $user    = $this->makeUser();
        $torneo  = $this->makeTournament($user);

        $this->assertInstanceOf(User::class, $torneo->creator);
        $this->assertEquals($user->id, $torneo->creator->id);
    }

    public function test_tournament_relacion_teams_retorna_coleccion(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $this->makeTeam($torneo, $user);
        $this->makeTeam($torneo, $this->makeUser());

        $this->assertCount(2, $torneo->teams);
        $this->assertInstanceOf(Team::class, $torneo->teams->first());
    }

    public function test_team_relacion_captain_retorna_user(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $team   = $this->makeTeam($torneo, $user);

        $this->assertInstanceOf(User::class, $team->captain);
        $this->assertEquals($user->id, $team->captain->id);
    }

    public function test_phase_relacion_matches_retorna_coleccion(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $phase  = $this->makePhase($torneo);
        $home   = $this->makeTeam($torneo, $user);
        $away   = $this->makeTeam($torneo, $this->makeUser());

        TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status'       => 'scheduled',
            'match_number' => 1,
        ]);

        $this->assertCount(1, $phase->matches);
        $this->assertInstanceOf(TournamentMatch::class, $phase->matches->first());
    }

    public function test_match_event_relacion_team_player_retorna_instancia(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $phase  = $this->makePhase($torneo);
        $home   = $this->makeTeam($torneo, $user);
        $away   = $this->makeTeam($torneo, $this->makeUser());

        $match = TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status'       => 'finished',
            'match_number' => 1,
        ]);

        $tp = TeamPlayer::create([
            'team_id' => $home->id,
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        $event = MatchEvent::create([
            'match_id'       => $match->id,
            'team_player_id' => $tp->id,
            'type'           => 'goal',
            'minute'         => 23,
        ]);

        $this->assertInstanceOf(TeamPlayer::class, $event->teamPlayer);
        $this->assertEquals($tp->id, $event->teamPlayer->id);
    }

    // --- Test tabla correcta para TournamentMatch ---

    public function test_tournament_match_usa_tabla_tournament_matches(): void
    {
        $model = new TournamentMatch();
        $this->assertEquals('tournament_matches', $model->getTable());
    }

    // --- Tests de helpers de estado ---

    public function test_tournament_helper_isOpen(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);

        $this->assertFalse($torneo->isOpen());

        $torneo->status = 'open';
        $this->assertTrue($torneo->isOpen());
    }

    public function test_team_helper_isApproved(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $team   = $this->makeTeam($torneo, $user);

        $this->assertTrue($team->isApproved());

        $team->status = 'pending';
        $this->assertFalse($team->isApproved());
        $this->assertTrue($team->isPending());
    }

    public function test_tournament_match_helper_hasResult(): void
    {
        $user   = $this->makeUser();
        $torneo = $this->makeTournament($user);
        $phase  = $this->makePhase($torneo);
        $home   = $this->makeTeam($torneo, $user);
        $away   = $this->makeTeam($torneo, $this->makeUser());

        $match = TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status'       => 'scheduled',
            'match_number' => 1,
        ]);

        $this->assertFalse($match->hasResult());

        $match->home_score = 2;
        $match->away_score = 1;
        $this->assertTrue($match->hasResult());
    }

    public function test_match_event_helper_isGoal(): void
    {
        $event       = new MatchEvent(['type' => 'goal']);
        $yellowCard  = new MatchEvent(['type' => 'yellow_card']);

        $this->assertTrue($event->isGoal());
        $this->assertFalse($yellowCard->isGoal());
        $this->assertTrue($yellowCard->isYellowCard());
    }

    public function test_invitation_helper_isExpired(): void
    {
        $inv = new TournamentInvitation([
            'status'     => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($inv->isExpired());

        $inv->expires_at = now()->addDay();
        $this->assertFalse($inv->isExpired());
    }
}
