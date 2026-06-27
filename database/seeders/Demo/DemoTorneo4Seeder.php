<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\TournamentInvitation;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\ClubMembershipService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * TORNEO 4 — Torneo Empresarial Café 2026 (OPEN — inscripciones abiertas).
 *
 * 8 cupos. 5 equipos inscritos: 4 aprobados + 1 pendiente de aprobación (llegó
 * ayer). 1 invitación enviada por email a un equipo que aún no está en FutGO.
 */
class DemoTorneo4Seeder extends Seeder
{
    public function run(): void
    {
        $organizador = DemoData::user(DemoData::ORGANIZADOR_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Torneo Empresarial Café 2026',
            'slug'                 => 'torneo-empresarial-cafe-2026',
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
            'tiebreaker_order'     => ['goal_difference', 'goals_for', 'head_to_head'],
            'match_duration'       => 90,
            'max_substitutions'    => 7,
            'mvp_enabled'          => true,
            'min_players_per_team' => 11,
            'max_players_per_team' => 25,
            'registration_fee'     => 180000,
            'visibility'           => 'public',
            'category'             => 'libre',
            'city'                 => 'Bucaramanga',
            'venue'                => 'Polideportivo Provenza',
            'registration_opens_at' => Carbon::now()->subWeek(),
            'registration_closes_at' => Carbon::now()->addWeeks(2),
            'starts_at'            => Carbon::now()->addWeeks(3),
            'ends_at'             => Carbon::now()->addWeeks(9),
            'description'          => 'Torneo entre empresas de la región. Inscripciones abiertas (cupo $180.000).',
            'created_by_user_id'   => $organizador->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $organizador->id]);

        $membership = app(ClubMembershipService::class);

        // 4 equipos aprobados.
        foreach (['los-guaduales-fc', 'palmira-united', 'deportivo-cafe', 'los-condores'] as $slug) {
            $team = $membership->enroll(DemoData::club($slug), $tournament);
            $team->update(['status' => 'approved']);
        }

        // 1 equipo pendiente de aprobación (se inscribió ayer).
        $pending = $membership->enroll(DemoData::club('atletico-guane'), $tournament);
        $pending->update(['status' => 'pending', 'created_at' => Carbon::now()->subDay()]);

        // Invitación por email a un equipo que aún no está en la plataforma.
        TournamentInvitation::create([
            'tournament_id'      => $tournament->id,
            'invited_by_user_id' => $organizador->id,
            'user_id'            => null,
            'email'              => 'fc.metropolitano@gmail.com',
            'token'              => Str::random(48),
            'status'             => 'pending',
            'expires_at'         => Carbon::now()->addWeeks(2),
        ]);

        $this->command?->info('   📝 Empresarial Café 2026 — inscripciones abiertas (4 aprobados, 1 pendiente).');
    }
}
