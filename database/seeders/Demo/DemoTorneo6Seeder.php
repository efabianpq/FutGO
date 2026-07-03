<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Team;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentSponsor;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Carbon\Carbon;
use Database\Seeders\Demo\Concerns\SeedsMatches;
use Illuminate\Database\Seeder;

/**
 * TORNEO 6 — Liga Escolar Sabana Sub-13 2026 (IN_PROGRESS — "en vivo").
 *
 * 6 colegios/escuelas de la Sabana de Bogotá (Chía, Cajicá, Zipaquirá,
 * Tocancipá, Sopó, Funza) en 2 grupos de 3. 4 de los 6 partidos de grupo ya
 * jugados, 2 programados a futuro con convocatorias activas — el torneo que más
 * se muestra en el video para administradores: crear → fixture → resultado en
 * vivo → posiciones → portal público. `visibility=public` y con patrocinadores
 * para poder compartirlo por WhatsApp con padres de familia.
 *
 * Es la edición Sub-13 de una liga escolar multi-categoría (Sub-11/Sub-13/
 * Sub-15) organizada por la misma coordinadora.
 */
class DemoTorneo6Seeder extends Seeder
{
    use SeedsMatches;

    /** Los 6 equipos participantes (orden = reparto en grupos A/B). */
    private const TEAM_SLUGS = [
        'colegio-san-rafael-chia', 'gimnasio-campestre-cajica', // A0, B0
        'liceo-sabana-zipaquira', 'instituto-tocancipa',        // A1, B1
        'escuela-futbol-sopo', 'real-funza-fc',                 // A2, B2
    ];

    private const CANCHA = 'Cancha Municipal de Chía';

    public function run(): void
    {
        $organizadora = DemoData::user(DemoData::SABANA_ORGANIZER_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Liga Escolar Sabana Sub-13 2026',
            'slug'                 => 'liga-escolar-sabana-sub13-2026',
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 3,
            'classifies_per_group' => 2,
            'max_teams'            => 6,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'tiebreaker_order'     => ['goal_difference', 'goals_for', 'head_to_head', 'fair_play', 'drawing'],
            'knockout_tiebreak'    => 'penalties',
            'match_duration'       => 60,
            'max_substitutions'    => 7,
            'mvp_enabled'          => true,
            'min_players_per_team' => 9,
            'max_players_per_team' => 20,
            'registration_fee'     => 0,
            'visibility'           => 'public',
            'category'             => 'sub15',
            'city'                 => 'Chía',
            'venue'                => self::CANCHA,
            'starts_at'            => Carbon::now()->subWeeks(3),
            'ends_at'              => Carbon::now()->addWeeks(5),
            'description'          => 'Liga escolar de la Sabana de Bogotá — edición Sub-13. Parte de la liga multi-'
                . 'categoría (Sub-11, Sub-13, Sub-15) que organiza la coordinación deportiva de la región con colegios '
                . 'y escuelas de fútbol de Chía, Cajicá, Zipaquirá, Tocancipá, Sopó y Funza.',
            'created_by_user_id'   => $organizadora->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $organizadora->id]);

        $teams = $this->enrollApproved($tournament, self::TEAM_SLUGS);
        $byId  = collect($teams)->keyBy(fn (Team $t) => $t->id);
        $teamOf = fn (int $id): Team => $byId->get($id);

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        $groupPhase = $tournament->phases()->where('type', 'groups')->orderBy('order')->first();
        $groups     = $groupPhase->groups()->orderBy('order')->orderBy('name')->get();

        // Cada grupo de 3 tiene 3 partidos: 0v1, 0v2, 1v2. Jugamos 0v1 y 1v2 de
        // cada grupo (4 en total); dejamos 0v2 de cada grupo programado a futuro.
        foreach ($groups as $gi => $group) {
            $matches = TournamentMatch::where('group_id', $group->id)->orderBy('match_number')->get();

            [$m01, $m02, $m12] = [$matches->get(0), $matches->get(1), $matches->get(2)];

            $resultsPlayed = $gi === 0
                ? [[2, 1], [1, 1]]   // Grupo A: San Rafael Chía 2-1 Liceo Zipaquirá / Liceo 1-1 Sopó
                : [[3, 0], [0, 2]];  // Grupo B: Gimnasio Cajicá 3-0 Instituto Tocancipá / Tocancipá 0-2 Real Funza

            if ($m01) {
                $home = $teamOf($m01->home_team_id);
                $away = $teamOf($m01->away_team_id);
                [$hs, $as] = $resultsPlayed[0];
                $this->playMatch($m01, $home, $away, $hs, $as, [
                    'when' => Carbon::now()->subWeeks(2), 'subs' => 4, 'mvp' => true, 'yellows' => 1,
                ]);
            }

            if ($m12) {
                $home = $teamOf($m12->home_team_id);
                $away = $teamOf($m12->away_team_id);
                [$hs, $as] = $resultsPlayed[1];
                $this->playMatch($m12, $home, $away, $hs, $as, [
                    'when' => Carbon::now()->subWeek(), 'subs' => 5, 'mvp' => true, 'yellows' => 0,
                ]);
            }

            if ($m02) {
                $when = Carbon::now()->addDays($gi === 0 ? 6 : 8)->setTime(9, 0);
                $this->scheduleMatch($m02, $when, self::CANCHA);
                if ($m02->home_team_id) {
                    $this->createCallUps($m02, $teamOf($m02->home_team_id), ['confirmado' => 12, 'convocado' => 3, 'declinado' => 1]);
                }
                if ($m02->away_team_id) {
                    $this->createCallUps($m02, $teamOf($m02->away_team_id), ['confirmado' => 11, 'convocado' => 4]);
                }
            }
        }

        app(StandingsCalculatorService::class)->recalculate($groupPhase);
        foreach ($teams as $team) {
            app(PlayerStatsCalculatorService::class)->recalculate($tournament, $team);
        }

        $this->createSponsors($tournament);

        $this->command?->info('   🏫 Liga Escolar Sabana Sub-13 2026 — 4/6 partidos de grupo jugados, próxima fecha programada.');
    }

    private function createSponsors(Tournament $tournament): void
    {
        foreach ([
            ['Tienda Deportiva La Sabana', 1],
            ['Panadería Central Chía', 2],
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
