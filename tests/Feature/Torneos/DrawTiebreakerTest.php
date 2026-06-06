<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\GroupTeam;
use App\Models\Torneos\Standing;
use App\Models\Torneos\StandingDraw;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentGroup;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Services\Torneos\StandingsCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sesión F — desempate por sorteo ('drawing'): reproducible y auditable, y solo
 * cuando ningún otro criterio del tiebreaker_order resuelve el empate.
 */
class DrawTiebreakerTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $admin = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);
        $t = Tournament::create([
            'name' => 'Copa ' . uniqid(), 'slug' => 'copa-' . uniqid(),
            'sport' => 'futbol', 'status' => 'in_progress', 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 2, 'classifies_per_group' => 1,
            'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
            // tiebreaker_order null → usa el default del modelo (incluye 'drawing').
        ]);
        $phase = TournamentPhase::create(['tournament_id' => $t->id, 'name' => 'Grupos', 'type' => 'groups', 'order' => 1, 'is_active' => true, 'status' => 'active']);
        $group = TournamentGroup::create(['phase_id' => $phase->id, 'name' => 'Grupo A', 'order' => 1]);

        $a = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $admin->id, 'name' => 'Equipo A', 'status' => 'approved']);
        $b = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $admin->id, 'name' => 'Equipo B', 'status' => 'approved']);
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $a->id]);
        GroupTeam::create(['group_id' => $group->id, 'team_id' => $b->id]);

        return [$t, $phase, $group, $a, $b];
    }

    public function test_el_sorteo_es_reproducible_y_se_audita(): void
    {
        [$t, $phase, $group, $a, $b] = $this->scaffold();

        // Empate ABSOLUTO: se enfrentaron y empataron 1-1 → mismos pts, DG, GF, h2h.
        TournamentMatch::create([
            'phase_id' => $phase->id, 'group_id' => $group->id,
            'home_team_id' => $a->id, 'away_team_id' => $b->id,
            'home_score' => 1, 'away_score' => 1, 'status' => 'finished', 'match_number' => 1,
        ]);

        $calc = app(StandingsCalculatorService::class);

        $calc->recalculate($phase);
        $firstA = Standing::where('group_id', $group->id)->where('team_id', $a->id)->value('position');
        $firstB = Standing::where('group_id', $group->id)->where('team_id', $b->id)->value('position');

        $calc->recalculate($phase);   // segundo recálculo
        $secondA = Standing::where('group_id', $group->id)->where('team_id', $a->id)->value('position');
        $secondB = Standing::where('group_id', $group->id)->where('team_id', $b->id)->value('position');

        // Reproducible: mismas posiciones en ambos recálculos.
        $this->assertSame($firstA, $secondA);
        $this->assertSame($firstB, $secondB);
        // Y son posiciones distintas (1 y 2), el empate quedó resuelto.
        $this->assertNotSame($firstA, $firstB);

        // Auditable: se registró el sorteo de los 2 equipos (sin duplicar al recalcular).
        $this->assertSame(2, StandingDraw::where('group_id', $group->id)->count());
    }

    public function test_el_sorteo_no_se_aplica_si_otro_criterio_resuelve_el_empate(): void
    {
        [$t, $phase, $group, $a, $b] = $this->scaffold();

        // A gana 3-0: difieren en puntos y diferencia de goles → NO hay sorteo.
        TournamentMatch::create([
            'phase_id' => $phase->id, 'group_id' => $group->id,
            'home_team_id' => $a->id, 'away_team_id' => $b->id,
            'home_score' => 3, 'away_score' => 0, 'winner_team_id' => $a->id,
            'status' => 'finished', 'match_number' => 1,
        ]);

        app(StandingsCalculatorService::class)->recalculate($phase);

        // Orden por mérito; A primero.
        $this->assertSame(1, Standing::where('group_id', $group->id)->where('team_id', $a->id)->value('position'));
        $this->assertSame(2, Standing::where('group_id', $group->id)->where('team_id', $b->id)->value('position'));

        // No se registró ningún sorteo.
        $this->assertSame(0, StandingDraw::where('group_id', $group->id)->count());
    }
}
