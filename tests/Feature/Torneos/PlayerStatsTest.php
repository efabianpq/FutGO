<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerStatsTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeTournamentAdmin(): User
    {
        return User::factory()->create([
'role' => 'user',        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
'role' => 'user',        ]);
    }

    /** Crea torneo round_robin con fixture y retorna [tournament, teams[]] */
    private function setupTournament(User $admin, int $teamCount = 4, int $matchDuration = 90): array
    {
        $tournament = Tournament::create([
            'name'                 => 'Test ' . uniqid(),
            'slug'                 => 'test-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'round_robin',
            'groups_count'         => 1,
            'teams_per_group'      => $teamCount,
            'classifies_per_group' => 1,
            'max_teams'            => $teamCount,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'match_duration'       => $matchDuration,
            'created_by_user_id'   => $admin->id,
        ]);

        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = [];
        for ($i = 0; $i < $teamCount; $i++) {
            $captain = $this->makeUser();
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $captain->id,
                'name'            => "Equipo $i",
                'status'          => 'approved',
            ]);
            // Capitán + 1 jugador extra por equipo
            $tp1 = TeamPlayer::create(['team_id' => $team->id, 'user_id' => $captain->id, 'status' => 'active']);
            $extra = $this->makeUser();
            $tp2 = TeamPlayer::create(['team_id' => $team->id, 'user_id' => $extra->id, 'status' => 'active', 'jersey_number' => 10]);
            $teams[] = ['team' => $team, 'players' => [$tp1, $tp2]];
        }

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        return [$tournament, $teams];
    }

    private function firstMatch(Tournament $tournament): TournamentMatch
    {
        return TournamentMatch::whereHas('phase', fn($q) => $q->where('tournament_id', $tournament->id))
            ->orderBy('match_number')
            ->first();
    }

    /** Guarda un resultado vía HTTP con lineups y eventos opcionales. */
    private function saveResult(
        User $admin,
        Tournament $tournament,
        TournamentMatch $match,
        int $homeScore,
        int $awayScore,
        array $lineups = [],
        array $events = []
    ): void {
        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            compact('home_score', 'away_score', 'lineups', 'events') +
                ['home_score' => $homeScore, 'away_score' => $awayScore]
        );
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_jugador_con_lineup_pero_sin_eventos_aparece_en_matches_played(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupTournament($admin);

        $match    = $this->firstMatch($tournament);
        $homeTeam = $teams[0]['team']->id === $match->home_team_id
            ? $teams[0] : $teams[1];
        $player   = $homeTeam['players'][1]; // jugador sin eventos planeados

        // Guardar resultado con lineup del jugador pero sin eventos
        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            [
                'home_score' => 1,
                'away_score' => 0,
                'lineups' => [
                    [
                        'team_player_id' => $player->id,
                        'team_id'        => $match->home_team_id,
                        'started'        => 1,
                        'minute_in'      => 0,
                        'minute_out'     => '',
                    ],
                ],
            ]
        );

        $stat = PlayerStat::where('tournament_id', $tournament->id)
            ->where('team_player_id', $player->id)
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->matches_played);
        $this->assertEquals(0, $stat->goals);
    }

    public function test_minutos_jugados_ya_no_se_miden_h11(): void
    {
        // H11: la medición de minutos jugados se retiró (torneos amateur no la llevan).
        // Aunque el lineup traiga minute_in/minute_out, minutes_played persiste en 0.
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupTournament($admin, 4, 90);

        $match  = $this->firstMatch($tournament);
        $player = $teams[0]['players'][1];

        $teamId = $teams[0]['team']->id;
        $isHome = $match->home_team_id === $teamId;

        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            [
                'home_score' => $isHome ? 1 : 0,
                'away_score' => $isHome ? 0 : 1,
                'lineups' => [
                    [
                        'team_player_id' => $player->id,
                        'team_id'        => $teamId,
                        'started'        => 0,
                        'minute_in'      => 30,
                        'minute_out'     => 70,
                    ],
                ],
            ]
        );

        $stat = PlayerStat::where('tournament_id', $tournament->id)
            ->where('team_player_id', $player->id)
            ->first();

        // El jugador SÍ cuenta como partido jugado (vía lineup), pero sin minutos.
        $this->assertEquals(1, $stat->matches_played);
        $this->assertEquals(0, $stat->minutes_played);
    }

    public function test_clean_sheets_con_lineup(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupTournament($admin);

        $match    = $this->firstMatch($tournament);
        $teamId   = $match->home_team_id;
        $player   = collect($teams)->first(fn($t) => $t['team']->id === $teamId)['players'][0];

        // Gana el equipo local sin recibir goles
        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            [
                'home_score' => 1,
                'away_score' => 0,
                'lineups' => [
                    [
                        'team_player_id' => $player->id,
                        'team_id'        => $teamId,
                        'started'        => 1,
                        'minute_in'      => 0,
                        'minute_out'     => '',
                    ],
                ],
            ]
        );

        $stat = PlayerStat::where('tournament_id', $tournament->id)
            ->where('team_player_id', $player->id)
            ->first();

        $this->assertEquals(1, $stat->clean_sheets);
        $this->assertEquals(1, $stat->wins);
    }

    public function test_tarjeta_roja_marca_jugador_como_inactive(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupTournament($admin);

        $match  = $this->firstMatch($tournament);
        $teamId = $match->home_team_id;
        $player = collect($teams)->first(fn($t) => $t['team']->id === $teamId)['players'][1];

        $this->assertEquals('active', $player->status);

        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            [
                'home_score' => 1,
                'away_score' => 0,
                'lineups' => [
                    [
                        'team_player_id' => $player->id,
                        'team_id'        => $teamId,
                        'started'        => 1,
                        'minute_in'      => 0,
                        'minute_out'     => '',
                    ],
                ],
                'events' => [
                    ['team_player_id' => $player->id, 'type' => 'red_card', 'minute' => 55],
                ],
            ]
        );

        $this->assertEquals('inactive', $player->fresh()->status);
    }

    public function test_wins_draws_losses_desde_lineup(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupTournament($admin, 4);

        [$m1, $m2] = TournamentMatch::whereHas('phase', fn($q) => $q->where('tournament_id', $tournament->id))
            ->orderBy('match_number')
            ->take(2)
            ->get()
            ->all();

        $teamId  = $m1->home_team_id;
        $player  = collect($teams)->first(fn($t) => $t['team']->id === $teamId)['players'][0];

        // Partido 1: gana
        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $m1]),
            [
                'home_score' => 2, 'away_score' => 0,
                'lineups' => [['team_player_id' => $player->id, 'team_id' => $teamId, 'started' => 1, 'minute_in' => 0, 'minute_out' => '']],
            ]
        );

        // Partido 2: empata (si el equipo está en m2 como home o away)
        $team2Id = $m2->home_team_id === $teamId ? $teamId : null;
        if ($team2Id) {
            $this->actingAs($admin)->post(
                route('admin.torneos.partidos.store', [$tournament, $m2]),
                [
                    'home_score' => 1, 'away_score' => 1,
                    'lineups' => [['team_player_id' => $player->id, 'team_id' => $teamId, 'started' => 1, 'minute_in' => 0, 'minute_out' => '']],
                ]
            );

            $stat = PlayerStat::where('tournament_id', $tournament->id)
                ->where('team_player_id', $player->id)
                ->first();

            $this->assertEquals(1, $stat->wins);
            $this->assertEquals(1, $stat->draws);
            $this->assertEquals(0, $stat->losses);
        } else {
            // Si no está en m2, al menos verificamos m1
            $stat = PlayerStat::where('tournament_id', $tournament->id)
                ->where('team_player_id', $player->id)
                ->first();
            $this->assertEquals(1, $stat->wins);
        }
    }

    public function test_vista_goleadores_solo_muestra_jugadores_con_al_menos_un_partido(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupTournament($admin);

        $match  = $this->firstMatch($tournament);
        $teamId = $match->home_team_id;
        $player = collect($teams)->first(fn($t) => $t['team']->id === $teamId)['players'][0];

        // Crear stat con 0 matches_played (no debe aparecer)
        PlayerStat::create([
            'tournament_id'  => $tournament->id,
            'team_player_id' => $player->id,
            'matches_played' => 0,
            'goals'          => 5, // goles pero sin partidos
        ]);

        $response = $this->actingAs($admin)
            ->get(route('torneos.estadisticas.index', $tournament));

        $response->assertOk();
        // El jugador con 0 partidos no debe estar en la tabla
        $response->assertDontSee($player->user->name . '');
    }

    public function test_perfil_individual_muestra_historial(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupTournament($admin);

        $match  = $this->firstMatch($tournament);
        $teamId = $match->home_team_id;
        $player = collect($teams)->first(fn($t) => $t['team']->id === $teamId)['players'][0];

        // Guardar resultado con lineup
        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            [
                'home_score' => 2, 'away_score' => 0,
                'lineups' => [
                    ['team_player_id' => $player->id, 'team_id' => $teamId, 'started' => 1, 'minute_in' => 0, 'minute_out' => ''],
                ],
                'events' => [
                    ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 15],
                ],
            ]
        );

        $response = $this->actingAs($admin)
            ->get(route('torneos.estadisticas.jugador', [$tournament, $player]));

        $response->assertOk();
        $response->assertSee($player->user->name);
        $response->assertSee('Gol'); // aparece en el historial
    }
}
