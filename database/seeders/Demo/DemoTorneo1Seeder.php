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
 * TORNEO 1 — Liga Medellín 2026 (IN_PROGRESS — eliminatoria activa).
 *
 * 8 equipos de Medellín y el Valle de Aburrá en 2 grupos de 4 (clasifican 2) →
 * semifinales jugadas → final y tercer puesto ya cruzados (auto por
 * `advanceKnockoutResults`) pero programados a futuro, sin jugar todavía. Sirve
 * para mostrar el bracket en vivo y el cierre de fase de grupos → eliminatoria.
 *
 * Incluye: convocatorias variadas en los últimos partidos de grupo, 1 baja por
 * lesión (roster_movement), 2 patrocinadores, recordatorios enviados y
 * convocatoria activa para la final pendiente.
 */
class DemoTorneo1Seeder extends Seeder
{
    use SeedsMatches;

    /** Los 8 equipos participantes (orden = reparto en grupos A/B). */
    private const TEAM_SLUGS = [
        'tigres-del-norte', 'academia-oro',       // A0, B0
        'belen-fc', 'laureles-atletico',          // A1, B1
        'poblado-united', 'bello-fc',             // A2, B2
        'itagui-fc', 'envigado-popular',          // A3, B3
    ];

    public function run(): void
    {
        $organizador = DemoData::user(DemoData::ORGANIZADOR_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Liga Medellín 2026',
            'slug'                 => 'liga-medellin-2026',
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
            'city'                 => 'Medellín',
            'venue'                => 'Unidad Deportiva Belén',
            'starts_at'            => Carbon::now()->subMonths(2),
            'ends_at'              => Carbon::now()->addWeeks(3),
            'description'          => 'Liga de fin de semana entre clubes de Medellín y el Valle de Aburrá. Temporada 2026.',
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

        $base = Carbon::now()->subMonths(2);

        // ── Grupo A: Tigres del Norte campeón de grupo, Poblado United 2do ───
        $resultsA = [
            [4, 0], // tigres vs belen
            [2, 1], // tigres vs poblado
            [3, 0], // tigres vs itagui
            [0, 2], // belen vs poblado
            [1, 1], // belen vs itagui
            [1, 3], // poblado vs itagui
        ];

        // ── Grupo B: Academia Oro 1ra, Laureles Atlético 2da ──────────────────
        $resultsB = [
            [3, 1], // academia vs laureles
            [4, 0], // academia vs bello
            [2, 0], // academia vs envigado
            [2, 1], // laureles vs bello
            [3, 0], // laureles vs envigado
            [1, 1], // bello vs envigado
        ];

        $groupResults = [$resultsA, $resultsB];

        foreach ($groups as $gi => $group) {
            $matches = TournamentMatch::where('group_id', $group->id)->orderBy('match_number')->get();
            foreach ($matches as $mi => $match) {
                [$hs, $as] = $groupResults[$gi][$mi];
                $home = $teamOf($match->home_team_id);
                $away = $teamOf($match->away_team_id);

                $opts = [
                    'when' => $base->copy()->addDays($gi * 1 + $mi * 4),
                    'subs' => ($mi >= 4) ? 3 : 1, // más cambios en los últimos
                    'mvp'  => true,
                ];

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

        // ── Semifinales jugadas (bracket en curso) ────────────────────────────
        $semi = $tournament->phases()->where('type', 'knockout')->orderBy('order')->first();
        $semiMatches = $semi->matches()->orderBy('match_number')->get();
        $semiScores = [[3, 2], [2, 0]];
        foreach ($semiMatches as $i => $m) {
            $home = $teamOf($m->home_team_id);
            $away = $teamOf($m->away_team_id);
            [$hs, $as] = $semiScores[$i];
            $opts = ['when' => Carbon::now()->subWeeks(2), 'subs' => 3, 'yellows' => 1, 'reds' => $i === 0 ? 1 : 0, 'mvp' => true];
            $this->playMatch($m, $home, $away, $hs, $as, $opts);
        }

        // Avanza ganadores → final y perdedores → tercer puesto (sin jugarlos aún).
        app(FixtureGeneratorService::class)->advanceKnockoutResults($semi->refresh());

        // ── Final + Tercer puesto: cruzados, PROGRAMADOS a futuro (sin jugar) ──
        $final = $tournament->phases()->where('type', 'knockout')->orderByDesc('order')->first();
        $fm = $final->matches()->orderBy('match_number')->first();
        if ($fm) {
            $this->scheduleMatch($fm, Carbon::now()->addWeeks(2)->setTime(15, 0), 'Unidad Deportiva Belén');
            if ($fm->home_team_id) {
                $this->createCallUps($fm, $teamOf($fm->home_team_id), ['confirmado' => 10, 'convocado' => 3]);
            }
            if ($fm->away_team_id) {
                $this->createCallUps($fm, $teamOf($fm->away_team_id), ['confirmado' => 9, 'convocado' => 4]);
            }
        }

        $third = $tournament->phases()->where('type', 'third_place')->first();
        if ($third) {
            $tm = $third->matches()->orderBy('match_number')->first();
            if ($tm && $tm->home_team_id) {
                $this->scheduleMatch($tm, Carbon::now()->addWeeks(2)->setTime(12, 0), 'Unidad Deportiva Belén');
            }
        }

        // ── Estadísticas individuales (todos los equipos, incluye knockout) ──
        foreach ($teams as $team) {
            app(PlayerStatsCalculatorService::class)->recalculate($tournament, $team);
        }

        // ── Datos transversales del torneo ───────────────────────────────────
        $this->createSponsors($tournament);
        $this->createInjuryMovement($tournament, $teams['belen-fc'], $organizador);

        $this->command?->info('   ⚔️  Liga Medellín 2026 — semifinales jugadas, final programada.');
    }

    private function createSponsors(Tournament $tournament): void
    {
        foreach ([
            ['Cervecería Local Laureles', 1],
            ['Almacén Deportivo El Poblado', 2],
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

    /** Baja por lesión de un jugador de Belén FC en la semana 3. */
    private function createInjuryMovement(Tournament $tournament, Team $belen, $actor): void
    {
        // Un suplente (no titular) para no alterar las estadísticas ya calculadas.
        $player = $belen->players()->where('status', 'active')->orderBy('id')->skip(12)->first()
            ?? $belen->players()->orderByDesc('id')->first();

        if (! $player) {
            return;
        }

        RosterMovement::create([
            'tournament_id'    => $tournament->id,
            'team_player_id'   => $player->id,
            'user_id'          => $player->user_id,
            'player_name'      => $player->displayName(),
            'type'             => 'baja',
            'from_team_id'     => $belen->id,
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
                ['sent_at' => Carbon::now()->subWeeks(3)],
            );
        }
    }
}
