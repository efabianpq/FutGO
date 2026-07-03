<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Team;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentMatchNotification;
use App\Models\Torneos\TournamentSponsor;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use Carbon\Carbon;
use Database\Seeders\Demo\Concerns\SeedsMatches;
use Illuminate\Database\Seeder;

/**
 * TORNEO 2 — Copa Élite Santander 2026 (IN_PROGRESS — eliminatoria activa).
 *
 * Copa directa de 8 equipos (cuartos de final). 3 cuartos ya jugados (Halcones FC
 * avanza con MVP del jugador estrella), 1 por jugarse en los próximos días con
 * convocatorias activas. Sirve para demostrar la eliminatoria en curso,
 * convocatorias y validación de credenciales (QR).
 */
class DemoTorneo2Seeder extends Seeder
{
    use SeedsMatches;

    private const TEAM_SLUGS = [
        'halcones-fc', 'los-condores', 'atletico-guane', 'academia-oro',
        'tigres-del-norte', 'caribe-fc', 'deportivo-cafe', 'independiente-sur',
    ];

    public function run(): void
    {
        $organizador = DemoData::user(DemoData::ORGANIZADOR_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Copa Élite Santander 2026',
            'slug'                 => 'copa-elite-santander-2026',
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'knockout_only',
            'classifies_per_group' => 1,
            'max_teams'            => 8,
            'third_place_match'    => true,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'tiebreaker_order'     => ['goal_difference', 'goals_for', 'head_to_head'],
            'knockout_tiebreak'    => 'penalties',
            'match_duration'       => 90,
            'max_substitutions'    => 5,
            'mvp_enabled'          => true,
            'min_players_per_team' => 11,
            'max_players_per_team' => 25,
            'registration_fee'     => 120000,
            'visibility'           => 'public',
            'category'             => 'libre',
            'city'                 => 'Bucaramanga',
            'venue'                => 'Complejo Deportivo La Cumbre',
            'starts_at'            => Carbon::now()->subWeeks(3),
            'ends_at'              => Carbon::now()->addWeeks(2),
            'description'          => 'Copa de eliminación directa entre los clubes más competitivos de Santander.',
            'created_by_user_id'   => $organizador->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $organizador->id]);

        $teams = $this->enrollApproved($tournament, self::TEAM_SLUGS);
        $byId  = collect($teams)->keyBy(fn (Team $t) => $t->id);
        $teamOf = fn (int $id): Team => $byId->get($id);

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        // Cuartos de final (primera ronda). Reescribimos los cruces para
        // controlar qué partidos quedan pendientes (Halcones y Atlético Guane).
        $qf = $tournament->phases()->where('type', 'knockout')->orderBy('order')->first();
        $qfMatches = $qf->matches()->orderBy('match_number')->get();

        $pairs = [
            ['tigres-del-norte', 'caribe-fc'],        // QF1 — jugado
            ['academia-oro', 'deportivo-cafe'],       // QF2 — jugado
            ['halcones-fc', 'los-condores'],          // QF3 — pendiente
            ['atletico-guane', 'independiente-sur'],  // QF4 — pendiente
        ];

        foreach ($qfMatches as $i => $m) {
            $m->update([
                'home_team_id'   => $teams[$pairs[$i][0]]->id,
                'away_team_id'   => $teams[$pairs[$i][1]]->id,
                'winner_team_id' => null,
                'home_score'     => null,
                'away_score'     => null,
                'status'         => 'scheduled',
            ]);
        }
        $qfMatches = $qf->matches()->orderBy('match_number')->get(); // recargar

        // QF1, QF2 y QF3 jugados.
        $this->playMatch($qfMatches[0], $teamOf($qfMatches[0]->home_team_id), $teamOf($qfMatches[0]->away_team_id), 2, 1, [
            'when' => Carbon::now()->subDays(4), 'subs' => 3, 'yellows' => 2, 'mvp' => true,
        ]);
        $this->playMatch($qfMatches[1], $teamOf($qfMatches[1]->home_team_id), $teamOf($qfMatches[1]->away_team_id), 3, 0, [
            'when' => Carbon::now()->subDays(3), 'subs' => 2, 'yellows' => 1, 'mvp' => true,
        ]);
        // QF3: Halcones FC gana con MVP del jugador estrella (Andrés Suárez, delantero).
        $estrellaTeamPlayerId = $teams['halcones-fc']->players()->where('user_id', DemoData::user(DemoData::ESTRELLA_EMAIL)->id)->value('id');
        $this->playMatch($qfMatches[2], $teamOf($qfMatches[2]->home_team_id), $teamOf($qfMatches[2]->away_team_id), 3, 1, [
            'when' => Carbon::now()->subDays(2), 'subs' => 3, 'yellows' => 1, 'mvpTeamPlayerId' => $estrellaTeamPlayerId,
        ]);

        // QF4 pendiente (próximos días) + convocatorias activas.
        $this->scheduleMatch($qfMatches[3], Carbon::now()->addDays(5)->setTime(19, 0), 'Cancha Sintética La Floresta');

        // Atlético Guane convocó 11 (7 confirmados, 4 sin responder).
        $this->createCallUps($qfMatches[3], $teams['atletico-guane'], ['confirmado' => 7, 'convocado' => 4]);
        $this->createCallUps($qfMatches[3], $teams['independiente-sur'], ['confirmado' => 8, 'convocado' => 3]);

        // Recordatorio programado para el próximo partido (QF4).
        foreach ($this->pickStarters($teams['atletico-guane'], 11)->whereNotNull('user_id') as $tp) {
            TournamentMatchNotification::firstOrCreate(
                ['user_id' => $tp->user_id, 'match_id' => $qfMatches[3]->id, 'type' => 'reminder'],
                ['sent_at' => Carbon::now()->subHours(20)],
            );
        }

        // Patrocinador.
        TournamentSponsor::create([
            'tournament_id' => $tournament->id,
            'name'          => 'Almacenes Éxito Cabecera',
            'sort_order'    => 1,
            'is_active'     => true,
        ]);

        foreach ($teams as $team) {
            app(PlayerStatsCalculatorService::class)->recalculate($tournament, $team);
        }

        $this->command?->info('   ⚔️  Copa Élite Santander 2026 — cuartos en curso (3 jugados, 1 pendiente).');
    }
}
