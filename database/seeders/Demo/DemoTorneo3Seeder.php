<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Team;
use App\Models\Torneos\TournamentSponsor;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Carbon\Carbon;
use Database\Seeders\Demo\Concerns\SeedsMatches;
use Illuminate\Database\Seeder;

/**
 * TORNEO 3 — Liga Barrial Bogotá 2026 (FINISHED).
 *
 * Liga "todos contra todos" entre 5 equipos por localidad de Bogotá (10
 * partidos, todos jugados). Campeón: Chapinero FC, invicto. Suba FC y Kennedy
 * United terminan EXACTAMENTE empatados en puntos/diferencia de gol/goles a
 * favor/enfrentamiento directo, forzando el sorteo determinista
 * (`standing_draws`). Sirve para mostrar posiciones cerradas, goleador, MVP y
 * tarjetas compartibles de un torneo ya terminado.
 */
class DemoTorneo3Seeder extends Seeder
{
    use SeedsMatches;

    // Orden = índice de equipo: 0 chapinero, 1 independiente sur, 2 suba, 3 kennedy, 4 bosa.
    private const TEAM_SLUGS = [
        'chapinero-fc', 'independiente-sur', 'suba-fc', 'kennedy-united', 'bosa-atletico',
    ];

    private const CANCHA = 'Polideportivo El Salitre';

    public function run(): void
    {
        $organizador = DemoData::user(DemoData::ORGANIZADOR_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Liga Barrial Bogotá 2026',
            'slug'                 => 'liga-barrial-bogota-2026',
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'round_robin',
            'max_teams'            => 5,
            'classifies_per_group' => 1,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'tiebreaker_order'     => ['goal_difference', 'goals_for', 'head_to_head'],
            'match_duration'       => 80,
            'max_substitutions'    => 5,
            'mvp_enabled'          => true,
            'min_players_per_team' => 11,
            'max_players_per_team' => 25,
            'registration_fee'     => 0,
            'visibility'           => 'public',
            'category'             => 'libre',
            'city'                 => 'Bogotá',
            'venue'                => self::CANCHA,
            'starts_at'            => Carbon::now()->subMonths(2),
            'ends_at'              => Carbon::now()->subWeeks(3),
            'description'          => 'Liga interbarrial entre localidades de Bogotá. Todos contra todos, temporada 2026.',
            'created_by_user_id'   => $organizador->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $organizador->id]);

        $teams = $this->enrollApproved($tournament, self::TEAM_SLUGS);
        $byId  = collect($teams)->keyBy(fn (Team $t) => $t->id);
        $teamOf = fn (int $id): Team => $byId->get($id);

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        $phase   = $tournament->phases()->where('type', 'groups')->first();
        $matches = $phase->matches()->orderBy('match_number')->get();

        // Identifica partidos por los clubs que enfrentan (independiente del orden interno).
        $play = function (string $a, string $b, int $sa, int $sb, Carbon $when, array $extra = []) use ($teams, $teamOf, $matches) {
            $idA = $teams[$a]->id; $idB = $teams[$b]->id;
            $m = $matches->first(fn ($x) =>
                ($x->home_team_id === $idA && $x->away_team_id === $idB) ||
                ($x->home_team_id === $idB && $x->away_team_id === $idA)
            );
            if (! $m) return;
            $opts = array_merge(['when' => $when, 'subs' => 2, 'mvp' => true, 'yellows' => 1], $extra);
            if ($m->home_team_id === $idA) {
                $this->playMatch($m, $teamOf($m->home_team_id), $teamOf($m->away_team_id), $sa, $sb, $opts);
            } else {
                $this->playMatch($m, $teamOf($m->home_team_id), $teamOf($m->away_team_id), $sb, $sa, $opts);
            }
        };

        $day = fn (int $weeksAgo) => Carbon::now()->subWeeks($weeksAgo)->setTime(15, 0);

        // Chapinero FC invicto, campeón (4W-0D-0L).
        $play('chapinero-fc', 'independiente-sur', 3, 0, $day(10));
        $play('chapinero-fc', 'suba-fc',           2, 0, $day(9));
        $play('chapinero-fc', 'kennedy-united',    2, 0, $day(8));
        $play('chapinero-fc', 'bosa-atletico',     4, 1, $day(7), ['reds' => 1]);

        // Independiente Sur y Bosa Atlético cierran la tabla (1 pto, se distinguen por GF).
        $play('independiente-sur', 'suba-fc',    0, 2, $day(9));
        $play('independiente-sur', 'kennedy-united', 0, 2, $day(8));
        $play('independiente-sur', 'bosa-atletico',  1, 1, $day(6));

        // Suba FC y Kennedy United terminan EXACTAMENTE empatados (2W-1D-1L, misma
        // diferencia de gol y mismos goles a favor) — fuerza el sorteo determinista.
        $play('suba-fc', 'kennedy-united', 1, 1, $day(5));
        $play('suba-fc', 'bosa-atletico',  2, 0, $day(4));
        $play('kennedy-united', 'bosa-atletico', 2, 0, $day(3));

        app(StandingsCalculatorService::class)->recalculate($phase);
        foreach ($teams as $team) {
            app(PlayerStatsCalculatorService::class)->recalculate($tournament, $team);
        }

        $this->createSponsors($tournament);

        $tournament->update(['status' => 'finished']);

        $this->command?->info('   🏆 Liga Barrial Bogotá 2026 finalizada — campeón Chapinero FC.');
    }

    private function createSponsors(Tournament $tournament): void
    {
        foreach ([
            ['Panadería La Castellana', 1],
            ['Ferretería Chapinero', 2],
        ] as [$name, $order]) {
            TournamentSponsor::create([
                'tournament_id' => $tournament->id,
                'name'          => $name,
                'link_url'      => null,
                'sort_order'    => $order,
                'is_active'     => true,
            ]);
        }
    }
}
