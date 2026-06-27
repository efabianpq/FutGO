<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Team;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Carbon\Carbon;
use Database\Seeders\Demo\Concerns\SeedsMatches;
use Illuminate\Database\Seeder;

/**
 * TORNEO 3 — Torneo Relámpago Nocturno (IN_PROGRESS — fase inicial).
 *
 * Liga "todos contra todos" de 5 equipos (10 partidos). Se jugaron 4; quedan 6
 * programados los viernes. Tigres del Norte lidera con 9 pts; empate en 2º/3º.
 */
class DemoTorneo3Seeder extends Seeder
{
    use SeedsMatches;

    // Orden = índice de equipo: 0 tigres, 1 academia, 2 halcones, 3 caribe, 4 palmira.
    private const TEAM_SLUGS = [
        'tigres-del-norte', 'academia-oro', 'halcones-fc', 'caribe-fc', 'palmira-united',
    ];

    private const CANCHA = 'Cancha Sintética La Flora — Iluminación nocturna';

    public function run(): void
    {
        $organizador = DemoData::user(DemoData::ORGANIZADOR_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Torneo Relámpago Nocturno',
            'slug'                 => 'torneo-relampago-nocturno',
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
            'city'                 => 'Medellín',
            'venue'                => self::CANCHA,
            'starts_at'            => Carbon::now()->subWeeks(3),
            'ends_at'              => Carbon::now()->addWeeks(4),
            'description'          => 'Liga nocturna de los viernes en cancha sintética. Todos contra todos.',
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
        $play = function (string $a, string $b, int $sa, int $sb, Carbon $when, bool $mvp = true) use ($teams, $teamOf, $matches) {
            $idA = $teams[$a]->id; $idB = $teams[$b]->id;
            $m = $matches->first(fn ($x) =>
                ($x->home_team_id === $idA && $x->away_team_id === $idB) ||
                ($x->home_team_id === $idB && $x->away_team_id === $idA)
            );
            if (! $m) return;
            // Orienta el marcador a como quedaron home/away en el fixture.
            if ($m->home_team_id === $idA) {
                $this->playMatch($m, $teamOf($m->home_team_id), $teamOf($m->away_team_id), $sa, $sb, ['when' => $when, 'subs' => 2, 'mvp' => $mvp, 'yellows' => 1]);
            } else {
                $this->playMatch($m, $teamOf($m->home_team_id), $teamOf($m->away_team_id), $sb, $sa, ['when' => $when, 'subs' => 2, 'mvp' => $mvp, 'yellows' => 1]);
            }
        };

        $fri = fn (int $weeksAgo) => Carbon::now()->subWeeks($weeksAgo)->next(Carbon::FRIDAY)->setTime(20, 0);

        // 4 partidos jugados: Tigres gana sus 3; empate Academia–Caribe.
        $play('tigres-del-norte', 'academia-oro', 2, 0, $fri(3));
        $play('tigres-del-norte', 'halcones-fc',  3, 1, $fri(3));
        $play('tigres-del-norte', 'palmira-united', 2, 0, $fri(2));
        $play('academia-oro', 'caribe-fc', 1, 1, $fri(2));

        // 6 partidos restantes: programados a futuro (sin convocatorias aún).
        $upcoming = $matches->whereNotIn('status', ['finished'])->values();
        foreach ($upcoming as $i => $m) {
            $this->scheduleMatch($m, Carbon::now()->addWeeks(intdiv($i, 2) + 1)->next(Carbon::FRIDAY)->setTime(20, 0), self::CANCHA);
        }

        app(StandingsCalculatorService::class)->recalculate($phase);
        foreach ($teams as $team) {
            app(PlayerStatsCalculatorService::class)->recalculate($tournament, $team);
        }

        $this->command?->info('   🌙 Relámpago Nocturno — 4/10 jugados, lidera Tigres del Norte.');
    }
}
