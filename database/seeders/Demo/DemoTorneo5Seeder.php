<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Tournament;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * TORNEO 5 — Torneo Privado Club Ejecutivos (DRAFT).
 *
 * Solo creado, sin equipos. Privado: NO aparece en el portal público. Demuestra
 * el estado borrador y la visibilidad privada.
 */
class DemoTorneo5Seeder extends Seeder
{
    public function run(): void
    {
        $organizador = DemoData::user(DemoData::ORGANIZADOR_EMAIL);

        $tournament = Tournament::create([
            'name'                 => 'Torneo Privado Club Ejecutivos',
            'slug'                 => 'torneo-privado-club-ejecutivos',
            'sport'                => 'futbol',
            'status'               => 'draft',
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
            'max_substitutions'    => 5,
            'mvp_enabled'          => false,
            'min_players_per_team' => 8,
            'max_players_per_team' => 20,
            'registration_fee'     => 0,
            'visibility'           => 'private',
            'category'             => 'libre',
            'city'                 => 'Bucaramanga',
            'venue'                => 'Club Campestre (por confirmar)',
            'starts_at'            => Carbon::now()->addMonths(2),
            'description'          => 'Torneo interno entre ejecutivos. Borrador en preparación (no público).',
            'created_by_user_id'   => $organizador->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $organizador->id]);

        $this->command?->info('   🗂️  Club Ejecutivos — borrador privado creado (sin equipos).');
    }
}
