<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\RosterMovement;
use App\Models\Torneos\Team;
use App\Models\Torneos\TournamentMatchNotification;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentSponsor;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PhaseClosureService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Carbon\Carbon;
use Database\Seeders\Demo\Concerns\SeedsMatches;
use Illuminate\Database\Seeder;

/**
 * TORNEO 1 — Liga Recreativa Bucaramanga 2025 (FINISHED).
 *
 * Referencia histórica completa: 8 equipos en 2 grupos de 4 (clasifican 2) →
 * semifinales → final + tercer puesto. Todos los partidos jugados con goles,
 * asistencias, tarjetas y MVP. Campeón: Halcones FC.
 *
 * Incluye: convocatorias variadas en los últimos partidos, 1 baja por lesión
 * (roster_movement), 2 patrocinadores, recordatorios enviados y un grupo con
 * empate absoluto que fuerza el sorteo determinista (standing_draws).
 */
class DemoTorneo1Seeder extends Seeder
{
    use SeedsMatches;

    /** Los 8 equipos participantes (orden = reparto en grupos A/B). */
    private const TEAM_SLUGS = [
        'halcones-fc', 'los-condores',        // A0, B0
        'deportivo-cafe', 'atletico-guane',   // A1, B1
        'tigres-del-norte', 'independiente-sur', // A2, B2
        'caribe-fc', 'academia-oro',          // A3, B3
    ];

    public function run(): void
    {
        $organizador = DemoData::user(DemoData::ORGANIZADOR_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Liga Recreativa Bucaramanga 2025',
            'slug'                 => 'liga-recreativa-bucaramanga-2025',
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 4,
            'classifies_per_group' => 2,
            'max_teams'            => 8,
            'third_place_match'    => true,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'tiebreaker_order'     => ['goal_difference', 'goals_for', 'head_to_head', 'fair_play', 'drawing'],
            'knockout_tiebreak'    => 'penalties',
            'match_duration'       => 90,
            'max_substitutions'    => 5,
            'mvp_enabled'          => true,
            'min_players_per_team' => 11,
            'max_players_per_team' => 25,
            'registration_fee'     => 0,
            'visibility'           => 'public',
            'category'             => 'libre',
            'city'                 => 'Bucaramanga',
            'venue'                => 'Cancha Marte — La Concordia',
            'starts_at'            => Carbon::now()->subMonths(10),
            'ends_at'              => Carbon::now()->subMonths(7),
            'description'          => 'Liga amateur de fin de semana en Bucaramanga. Temporada 2025.',
            'created_by_user_id'   => $organizador->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $organizador->id]);

        $teams = $this->enrollApproved($tournament, self::TEAM_SLUGS);
        $byId  = collect($teams)->keyBy(fn (Team $t) => $t->id);

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        $groupPhase = $tournament->phases()->where('type', 'groups')->orderBy('order')->first();
        $groups     = $groupPhase->groups()->orderBy('order')->orderBy('name')->get();

        // Helper local: resuelve el Team de un id.
        $teamOf = fn (int $id): Team => $byId->get($id);

        $base = Carbon::now()->subMonths(9);

        // ── Grupo A: Halcones campeón de grupo, Tigres 2do ───────────────────
        $resultsA = [
            [5, 0, ['mvpSlug' => 'halcones-fc']],   // halcones vs deportivo
            [3, 1, ['mvpSlug' => 'halcones-fc']],   // halcones vs tigres
            [4, 0, ['mvpSlug' => 'halcones-fc']],   // halcones vs caribe
            [0, 2, []],                              // deportivo vs tigres
            [1, 1, []],                              // deportivo vs caribe
            [3, 1, []],                              // tigres vs caribe
        ];

        // ── Grupo B: Academia 1ro, Cóndores 2do, Guane/Indep empate absoluto ─
        $resultsB = [
            [2, 0, ['yellows' => 0]], // condores vs guane
            [2, 0, ['yellows' => 0]], // condores vs indep
            [0, 2, ['yellows' => 1]], // condores vs academia
            [1, 1, ['yellows' => 0]], // guane vs indep
            [0, 2, ['yellows' => 0]], // guane vs academia
            [0, 2, ['yellows' => 0]], // indep vs academia
        ];

        $groupResults = [$resultsA, $resultsB];

        foreach ($groups as $gi => $group) {
            $matches = TournamentMatch::where('group_id', $group->id)->orderBy('match_number')->get();
            foreach ($matches as $mi => $match) {
                [$hs, $as, $opts] = $groupResults[$gi][$mi];
                $home = $teamOf($match->home_team_id);
                $away = $teamOf($match->away_team_id);

                $opts['when'] = $base->copy()->addDays($gi * 1 + $mi * 4);
                $opts['subs'] = ($mi >= 4) ? 3 : 1; // más cambios en los últimos
                $opts = $this->resolveMvp($opts, $home, $away);

                $this->playMatch($match, $home, $away, $hs, $as, $opts);
            }
        }

        // Convocatorias variadas en los últimos 2 partidos de cada grupo.
        foreach ($groups as $group) {
            $last = TournamentMatch::where('group_id', $group->id)->orderByDesc('match_number')->limit(2)->get();
            foreach ($last as $m) {
                $this->createCallUps($m, $teamOf($m->home_team_id), ['confirmado' => 9, 'convocado' => 2, 'declinado' => 2]);
                $this->createCallUps($m, $teamOf($m->away_team_id), ['confirmado' => 8, 'convocado' => 3, 'declinado' => 1]);
                // Recordatorios enviados a los titulares registrados.
                $this->sendReminders($m, $teamOf($m->home_team_id));
                $this->sendReminders($m, $teamOf($m->away_team_id));
            }
        }

        // ── Standings + cierre de grupos → semifinales ───────────────────────
        app(StandingsCalculatorService::class)->recalculate($groupPhase);
        app(PhaseClosureService::class)->closeGroupPhase($groupPhase->refresh());

        // ── Semifinales: Halcones vs Cóndores / Academia vs Tigres ───────────
        $semi = $tournament->phases()->where('type', 'knockout')->orderBy('order')->first();
        $semiMatches = $semi->matches()->orderBy('match_number')->get();
        $semiScores = [[3, 1], [2, 1]]; // SF1 halcones gana, SF2 academia gana
        foreach ($semiMatches as $i => $m) {
            $home = $teamOf($m->home_team_id);
            $away = $teamOf($m->away_team_id);
            [$hs, $as] = $semiScores[$i];
            $opts = ['when' => Carbon::now()->subMonths(8), 'subs' => 3, 'yellows' => 1, 'reds' => $i === 0 ? 1 : 0];
            if ($home->club_id === DemoData::club('halcones-fc')->id) {
                $opts['mvpTeamPlayerId'] = $this->estrellaTeamPlayerId($home);
            }
            $this->playMatch($m, $home, $away, $hs, $as, $opts);
        }

        // Avanza ganadores → final y perdedores → tercer puesto.
        app(FixtureGeneratorService::class)->advanceKnockoutResults($semi->refresh());

        // ── Final + Tercer puesto ────────────────────────────────────────────
        $final = $tournament->phases()->where('type', 'knockout')->orderByDesc('order')->first();
        $fm = $final->matches()->orderBy('match_number')->first();
        $fhome = $teamOf($fm->home_team_id);
        $faway = $teamOf($fm->away_team_id);
        $fopts = ['when' => Carbon::now()->subMonths(7), 'subs' => 3, 'yellows' => 2, 'mvp' => true];
        if ($fhome->club_id === DemoData::club('halcones-fc')->id) {
            $fopts['mvpTeamPlayerId'] = $this->estrellaTeamPlayerId($fhome);
            $this->playMatch($fm, $fhome, $faway, 3, 2, $fopts);
        } else {
            $fopts['mvpTeamPlayerId'] = $this->estrellaTeamPlayerId($faway);
            $this->playMatch($fm, $fhome, $faway, 2, 3, $fopts);
        }

        $third = $tournament->phases()->where('type', 'third_place')->first();
        if ($third) {
            $tm = $third->matches()->orderBy('match_number')->first();
            if ($tm && $tm->home_team_id) {
                $this->playMatch($tm, $teamOf($tm->home_team_id), $teamOf($tm->away_team_id), 2, 1, [
                    'when' => Carbon::now()->subMonths(7), 'subs' => 2, 'yellows' => 1, 'mvp' => true,
                ]);
            }
        }

        // ── Estadísticas individuales (todos los equipos, incluye knockout) ──
        foreach ($teams as $team) {
            app(PlayerStatsCalculatorService::class)->recalculate($tournament, $team);
        }

        // ── Datos transversales del torneo ───────────────────────────────────
        $this->createSponsors($tournament);
        $this->createInjuryMovement($tournament, $teams['los-condores'], $organizador);

        // ── Cierre del torneo ────────────────────────────────────────────────
        $tournament->update(['status' => 'finished']);

        $this->command?->info('   🏆 Liga Recreativa 2025 finalizada — campeón Halcones FC.');
    }

    /** Convierte mvpSlug → mvpTeamPlayerId del titular estrella/registrado. */
    private function resolveMvp(array $opts, Team $home, Team $away): array
    {
        if (empty($opts['mvpSlug'])) {
            return $opts;
        }
        $clubId = DemoData::club($opts['mvpSlug'])->id;
        $team   = $home->club_id === $clubId ? $home : ($away->club_id === $clubId ? $away : null);
        unset($opts['mvpSlug']);
        if ($team) {
            $opts['mvpTeamPlayerId'] = $this->estrellaTeamPlayerId($team) ?? $this->pickStarters($team, 1)->first()?->id;
        }
        return $opts;
    }

    /** team_player_id del jugador estrella dentro del equipo dado (o null). */
    private function estrellaTeamPlayerId(Team $team): ?int
    {
        $estrella = DemoData::user(DemoData::ESTRELLA_EMAIL);
        return $team->players()->where('user_id', $estrella->id)->value('id');
    }

    private function createSponsors(Tournament $tournament): void
    {
        foreach ([
            ['Cerveza Club Colombia', 1],
            ['Pinturas Tito Pabón', 2],
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

    /** Baja por lesión de un jugador de Los Cóndores en la semana 3. */
    private function createInjuryMovement(Tournament $tournament, Team $condores, $actor): void
    {
        // Un suplente (no titular) para no alterar las estadísticas ya calculadas.
        $player = $condores->players()->where('status', 'active')->orderBy('id')->skip(12)->first()
            ?? $condores->players()->orderByDesc('id')->first();

        if (! $player) {
            return;
        }

        RosterMovement::create([
            'tournament_id'    => $tournament->id,
            'team_player_id'   => $player->id,
            'user_id'          => $player->user_id,
            'player_name'      => $player->displayName(),
            'type'             => 'baja',
            'from_team_id'     => $condores->id,
            'to_team_id'       => null,
            'acted_by_user_id' => $actor->id,
            'notes'            => 'Lesión de rodilla en la semana 3 — baja por el resto de la temporada.',
        ]);

        $player->update(['status' => 'inactive']);
    }

    /** Crea recordatorios "enviados" para los titulares registrados de un equipo. */
    private function sendReminders(TournamentMatch $match, Team $team): void
    {
        $players = $this->pickStarters($team, 11)->whereNotNull('user_id');
        foreach ($players as $tp) {
            TournamentMatchNotification::firstOrCreate(
                ['user_id' => $tp->user_id, 'match_id' => $match->id, 'type' => 'reminder'],
                ['sent_at' => Carbon::now()->subMonths(8)],
            );
        }
    }
}
