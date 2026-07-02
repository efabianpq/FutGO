<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Services\Torneos\ClubStatsService;
use App\Services\Torneos\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deuda #3 — stats de club cacheadas en club_stats en vez de recalcularse en
 * cada lectura del perfil.
 */
class ClubStatsTest extends TestCase
{
    use RefreshDatabase;

    /** Club con 2 participaciones (torneos), partidos finalizados y goleadores. */
    private function clubWithHistory(): array
    {
        $captain = User::factory()->create([]);
        $club = Club::create(['name' => 'Halcones FC', 'slug' => 'halcones-fc-' . uniqid(), 'captain_user_id' => $captain->id, 'created_by_user_id' => $captain->id]);

        $t1 = Tournament::create([
            'name' => 'Liga A ' . uniqid(), 'slug' => 'liga-a-' . uniqid(), 'sport' => 'futbol',
            'status' => 'in_progress', 'format' => 'round_robin', 'groups_count' => 1, 'teams_per_group' => 2,
            'classifies_per_group' => 1, 'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $captain->id,
        ]);
        $phase1 = TournamentPhase::create(['tournament_id' => $t1->id, 'name' => 'F', 'type' => 'groups', 'order' => 1, 'is_active' => true, 'status' => 'active']);
        $home1 = Team::create(['tournament_id' => $t1->id, 'club_id' => $club->id, 'captain_user_id' => $captain->id, 'name' => 'Halcones FC', 'status' => 'approved']);
        $away1 = Team::create(['tournament_id' => $t1->id, 'captain_user_id' => $captain->id, 'name' => 'Rival 1', 'status' => 'approved']);
        // Ganado 3-1 y perdido 0-2.
        TournamentMatch::create(['phase_id' => $phase1->id, 'home_team_id' => $home1->id, 'away_team_id' => $away1->id, 'home_score' => 3, 'away_score' => 1, 'winner_team_id' => $home1->id, 'status' => 'finished', 'match_number' => 1]);
        TournamentMatch::create(['phase_id' => $phase1->id, 'home_team_id' => $away1->id, 'away_team_id' => $home1->id, 'home_score' => 2, 'away_score' => 0, 'winner_team_id' => $away1->id, 'status' => 'finished', 'match_number' => 2]);

        $tp1 = TeamPlayer::create(['team_id' => $home1->id, 'user_id' => $captain->id, 'status' => 'active']);
        PlayerStat::create(['tournament_id' => $t1->id, 'team_player_id' => $tp1->id, 'goals' => 4, 'matches_played' => 2]);

        $t2 = Tournament::create([
            'name' => 'Liga B ' . uniqid(), 'slug' => 'liga-b-' . uniqid(), 'sport' => 'futbol',
            'status' => 'in_progress', 'format' => 'round_robin', 'groups_count' => 1, 'teams_per_group' => 2,
            'classifies_per_group' => 1, 'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $captain->id,
        ]);
        $phase2 = TournamentPhase::create(['tournament_id' => $t2->id, 'name' => 'F', 'type' => 'groups', 'order' => 1, 'is_active' => true, 'status' => 'active']);
        $home2 = Team::create(['tournament_id' => $t2->id, 'club_id' => $club->id, 'captain_user_id' => $captain->id, 'name' => 'Halcones FC', 'status' => 'approved']);
        $away2 = Team::create(['tournament_id' => $t2->id, 'captain_user_id' => $captain->id, 'name' => 'Rival 2', 'status' => 'approved']);
        // Empate 1-1 en el segundo torneo.
        TournamentMatch::create(['phase_id' => $phase2->id, 'home_team_id' => $home2->id, 'away_team_id' => $away2->id, 'home_score' => 1, 'away_score' => 1, 'status' => 'finished', 'match_number' => 1]);

        $tp2 = TeamPlayer::create(['team_id' => $home2->id, 'user_id' => $captain->id, 'status' => 'active']);
        PlayerStat::create(['tournament_id' => $t2->id, 'team_player_id' => $tp2->id, 'goals' => 1, 'matches_played' => 1]);

        return [$club, $t1, $t2];
    }

    public function test_refresh_for_club_agrega_partidos_de_todos_los_torneos(): void
    {
        [$club] = $this->clubWithHistory();

        $stat = app(ClubStatsService::class)->refreshForClub($club);

        $this->assertSame(3, $stat->played);
        $this->assertSame(1, $stat->won);
        $this->assertSame(1, $stat->drawn);
        $this->assertSame(1, $stat->lost);
        $this->assertSame(3 + 0 + 1, $stat->goals_for);
        $this->assertSame(1 + 2 + 1, $stat->goals_against);
        $this->assertNotNull($stat->calculated_at);

        $this->assertCount(1, $stat->top_scorers);
        $this->assertSame(5, $stat->top_scorers[0]['goals']); // 4 + 1 goles del capitán en ambos torneos
    }

    public function test_el_perfil_del_club_lee_de_la_cache_sin_recalcular(): void
    {
        [$club] = $this->clubWithHistory();

        app(ClubStatsService::class)->refreshForClub($club);

        // Alteramos los datos subyacentes SIN refrescar la cache: el perfil
        // debe seguir mostrando el valor cacheado (played=3), no el nuevo (4).
        TournamentMatch::create([
            'phase_id' => TournamentPhase::first()->id,
            'home_team_id' => Team::where('club_id', $club->id)->first()->id,
            'away_team_id' => Team::whereNull('club_id')->first()->id,
            'home_score' => 5, 'away_score' => 0, 'status' => 'finished', 'match_number' => 99,
        ]);

        $response = $this->actingAs(User::factory()->create([]))
            ->get(route('torneos.clubes.show', $club));

        $response->assertOk();
        $this->assertSame(3, ClubStat::where('club_id', $club->id)->first()->played);
    }

    public function test_el_perfil_del_club_calcula_una_vez_si_nunca_hubo_cache(): void
    {
        [$club] = $this->clubWithHistory();

        $this->assertSame(0, ClubStat::where('club_id', $club->id)->count());

        $response = $this->actingAs(User::factory()->create([]))
            ->get(route('torneos.clubes.show', $club));

        $response->assertOk();
        $stat = ClubStat::where('club_id', $club->id)->first();
        $this->assertNotNull($stat);
        $this->assertSame(3, $stat->played);
    }

    public function test_consolidar_torneo_refresca_club_stats_de_los_participantes(): void
    {
        [$club, $t1] = $this->clubWithHistory();

        $this->assertSame(0, ClubStat::where('club_id', $club->id)->count());

        app(ReputationService::class)->consolidateTournament($t1->fresh());

        $stat = ClubStat::where('club_id', $club->id)->first();
        $this->assertNotNull($stat);
        $this->assertSame(3, $stat->played); // ya cuenta los partidos de AMBOS torneos, no solo t1
    }

    public function test_rebuild_all_reconstruye_club_stats_de_todos_los_clubes(): void
    {
        [$club] = $this->clubWithHistory();

        app(ReputationService::class)->rebuildAll();

        $this->assertSame(1, ClubStat::where('club_id', $club->id)->count());
        $this->assertSame(3, ClubStat::where('club_id', $club->id)->first()->played);
    }
}
