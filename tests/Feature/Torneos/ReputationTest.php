<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Achievement;
use App\Models\Torneos\FutgoRanking;
use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\Torneos\UserAchievement;
use App\Models\User;
use App\Services\Torneos\AchievementService;
use App\Services\Torneos\FairPlayService;
use App\Services\Torneos\PlayerCareerStatsService;
use App\Services\Torneos\RankingService;
use App\Services\Torneos\SeasonHistoryService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sesión F — reputación: ranking, logros, fair play e historial de temporadas.
 */
class ReputationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AchievementSeeder::class);   // catálogo de logros disponible
    }

    private function tournament(array $attrs = []): Tournament
    {
        $admin = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);

        return Tournament::create(array_merge([
            'name' => 'Copa ' . uniqid(), 'slug' => 'copa-' . uniqid(),
            'sport' => 'futbol', 'status' => 'in_progress', 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 4, 'classifies_per_group' => 1,
            'max_teams' => 4, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ], $attrs));
    }

    /** Crea un jugador con un player_stat en un torneo. Devuelve el usuario. */
    private function playerWithStats(Tournament $t, array $stats, ?string $name = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'modules' => 'torneos', 'name' => $name ?? ('Jugador ' . uniqid())]);
        $team = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $user->id, 'name' => 'Eq ' . uniqid(), 'status' => 'approved']);
        $tp = TeamPlayer::create(['team_id' => $team->id, 'user_id' => $user->id, 'status' => 'active']);
        PlayerStat::create(array_merge([
            'tournament_id' => $t->id, 'team_player_id' => $tp->id,
            'goals' => 0, 'assists' => 0, 'mvps' => 0, 'wins' => 0, 'clean_sheets' => 0,
            'yellow_cards' => 0, 'red_cards' => 0, 'matches_played' => 0,
        ], $stats));

        return $user;
    }

    // ── Ranking ───────────────────────────────────────────────────────────

    public function test_el_ranking_de_jugadores_ordena_segun_la_formula(): void
    {
        $t = $this->tournament(['city' => 'Cali', 'category' => 'libre']);
        $a = $this->playerWithStats($t, ['goals' => 10, 'matches_played' => 5], 'Crack A');
        $b = $this->playerWithStats($t, ['goals' => 2, 'matches_played' => 2], 'Suplente B');

        app(FairPlayService::class)->rebuild();   // 100 (sin tarjetas)
        app(RankingService::class)->rebuild();

        $rank = FutgoRanking::players()->forScope('global')->orderBy('position')->get();

        // Fórmula: A = 10·4 + 5·1 + 100·0.5 = 95 ; B = 2·4 + 2·1 + 50 = 60.
        $this->assertSame($a->id, (int) $rank[0]->subject_id);
        $this->assertSame(95, $rank[0]->score);
        $this->assertSame($b->id, (int) $rank[1]->subject_id);
        $this->assertSame(60, $rank[1]->score);
    }

    public function test_el_ranking_filtra_por_ciudad_y_categoria(): void
    {
        $cali   = $this->tournament(['city' => 'Cali', 'category' => 'libre']);
        $bogota = $this->tournament(['city' => 'Bogotá', 'category' => 'veteranos']);

        $x = $this->playerWithStats($cali, ['goals' => 5, 'matches_played' => 3], 'Caleño');
        $y = $this->playerWithStats($bogota, ['goals' => 8, 'matches_played' => 3], 'Bogotano');

        app(RankingService::class)->rebuild();

        $caliIds = FutgoRanking::players()->forScope('city', 'Cali')->pluck('subject_id')->all();
        $this->assertContains($x->id, $caliIds);
        $this->assertNotContains($y->id, $caliIds);

        $vetIds = FutgoRanking::players()->forScope('category', 'veteranos')->pluck('subject_id')->all();
        $this->assertContains($y->id, $vetIds);
        $this->assertNotContains($x->id, $vetIds);
    }

    // ── Logros ────────────────────────────────────────────────────────────

    public function test_un_logro_se_otorga_automaticamente_al_cumplir_la_condicion(): void
    {
        $t = $this->tournament();
        $user = $this->playerWithStats($t, ['matches_played' => 50]);

        app(PlayerCareerStatsService::class)->refreshForUser($user);
        $granted = app(AchievementService::class)->evaluateForUser($user);

        $codes = collect($granted)->pluck('code')->all();
        $this->assertContains('veterano_50', $codes);
        $this->assertContains('debut', $codes);   // 1 partido también se cumple

        $this->assertTrue(
            $user->achievements()->where('code', 'veterano_50')->exists()
        );
    }

    public function test_un_logro_no_se_otorga_dos_veces(): void
    {
        $t = $this->tournament();
        $user = $this->playerWithStats($t, ['matches_played' => 50]);
        app(PlayerCareerStatsService::class)->refreshForUser($user);

        app(AchievementService::class)->evaluateForUser($user);
        $secondRun = app(AchievementService::class)->evaluateForUser($user);

        // En la segunda corrida no se otorga ninguno nuevo.
        $this->assertSame([], collect($secondRun)->pluck('code')->all());

        $veterano = Achievement::where('code', 'veterano_50')->first();
        $this->assertSame(1, UserAchievement::where('user_id', $user->id)->where('achievement_id', $veterano->id)->count());
    }

    // ── Fair Play ───────────────────────────────────────────────────────────

    public function test_el_fair_play_baja_con_tarjetas_e_inasistencias(): void
    {
        $t = $this->tournament();
        $user = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);
        $team = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $user->id, 'name' => 'FP', 'status' => 'approved']);
        $tp = TeamPlayer::create(['team_id' => $team->id, 'user_id' => $user->id, 'status' => 'active']);

        // Limpio = 100.
        $service = app(FairPlayService::class);
        $this->assertSame(100, $service->refreshForUser($user)->score);

        // Tarjetas: 2 amarillas + 1 roja → 100 − 6 − 10 = 84.
        PlayerStat::create([
            'tournament_id' => $t->id, 'team_player_id' => $tp->id,
            'yellow_cards' => 2, 'red_cards' => 1, 'matches_played' => 5,
        ]);
        $this->assertSame(84, $service->refreshForUser($user)->score);

        // Inasistencias: 1 declinada + 1 convocado a partido finalizado → −10 → 74.
        $phase = TournamentPhase::create(['tournament_id' => $t->id, 'name' => 'F', 'type' => 'groups', 'order' => 1, 'is_active' => true, 'status' => 'active']);
        $m1 = TournamentMatch::create(['phase_id' => $phase->id, 'home_team_id' => $team->id, 'away_team_id' => $team->id, 'status' => 'finished', 'match_number' => 1]);
        $m2 = TournamentMatch::create(['phase_id' => $phase->id, 'home_team_id' => $team->id, 'away_team_id' => $team->id, 'status' => 'finished', 'match_number' => 2]);
        MatchCallUp::create(['match_id' => $m1->id, 'team_player_id' => $tp->id, 'team_id' => $team->id, 'status' => 'declinado']);
        MatchCallUp::create(['match_id' => $m2->id, 'team_player_id' => $tp->id, 'team_id' => $team->id, 'status' => 'convocado']);

        $score = $service->refreshForUser($user);
        $this->assertSame(2, $score->absences);
        $this->assertSame(74, $score->score);
    }

    // ── Historial de temporadas ───────────────────────────────────────────

    public function test_el_historial_de_temporadas_muestra_la_participacion(): void
    {
        $t2024 = $this->tournament(['starts_at' => '2024-03-01 10:00:00']);
        $t2025 = $this->tournament(['starts_at' => '2025-03-01 10:00:00']);

        $user = $this->playerWithStats($t2024, ['goals' => 3, 'matches_played' => 4]);
        // Mismo usuario en 2025: agrega otra inscripción + stat.
        $team = Team::create(['tournament_id' => $t2025->id, 'captain_user_id' => $user->id, 'name' => 'Eq25', 'status' => 'approved']);
        $tp = TeamPlayer::create(['team_id' => $team->id, 'user_id' => $user->id, 'status' => 'active']);
        PlayerStat::create(['tournament_id' => $t2025->id, 'team_player_id' => $tp->id, 'goals' => 7, 'matches_played' => 6]);

        $seasons = app(SeasonHistoryService::class)->forUser($user);

        $this->assertCount(2, $seasons);
        $this->assertSame(2025, $seasons[0]['season']);    // más reciente primero
        $this->assertSame(7, $seasons[0]['goals']);
        $this->assertSame(6, $seasons[0]['matches']);
        $this->assertSame(2024, $seasons[1]['season']);
        $this->assertSame(3, $seasons[1]['goals']);
    }
}
