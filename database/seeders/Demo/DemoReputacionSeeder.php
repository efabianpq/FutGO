<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Club;
use App\Models\Torneos\CredentialValidation;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\ReputationService;
use Illuminate\Database\Seeder;

/**
 * BLOQUE 4 — Datos transversales de reputación.
 *
 * Consolida con los SERVICIOS REALES toda la capa de reputación a partir de los
 * partidos ya sembrados: acumulado histórico (player_career_stats), fair play
 * (fair_play_scores), logros (user_achievements) y ranking (futgo_rankings).
 *
 * Suma además las validaciones de credencial (QR) de la Copa Élite.
 */
class DemoReputacionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('   📊 Consolidando reputación (carrera, fair play, logros, ranking)...');
        app(ReputationService::class)->rebuildAll();

        $this->command?->info('   🪪 Validaciones de credencial (QR) en la Copa Élite...');
        $this->seedCredentialValidations();
    }

    /**
     * 5 validaciones de credencial del árbitro antes de un partido de la Copa Élite:
     * 2 habilitado, 1 no_habilitado (equipo eliminado), 1 no_encontrado, 1 manual.
     */
    private function seedCredentialValidations(): void
    {
        $copa    = Tournament::where('slug', 'copa-elite-santander-2026')->first();
        $arbitro = DemoData::user(DemoData::ARBITRO_EMAIL);
        if (! $copa) {
            return;
        }

        $halcones = $this->registeredMembers('halcones-fc', 3);  // activos en la Copa
        $caribe   = $this->registeredMembers('caribe-fc', 1);    // eliminado en cuartos

        $rows = [];

        // 2 habilitado (QR) — titulares de un equipo activo.
        foreach ($halcones->take(2) as $u) {
            $rows[] = [$u->futgo_id, $u->id, 'habilitado', 'qr'];
        }

        // 1 no_habilitado — jugador de un equipo ya eliminado.
        if ($caribe->isNotEmpty()) {
            $u = $caribe->first();
            $rows[] = [$u->futgo_id, $u->id, 'no_habilitado', 'qr'];
        }

        // 1 no_encontrado — identificador inventado, sin usuario.
        $rows[] = ['FG-ZZ0099', null, 'no_encontrado', 'qr'];

        // 1 habilitado por ingreso MANUAL del identificador (sin QR).
        if ($halcones->count() >= 3) {
            $u = $halcones->get(2);
            $rows[] = [$u->futgo_id, $u->id, 'habilitado', 'manual'];
        }

        foreach ($rows as [$futgoId, $userId, $result, $method]) {
            CredentialValidation::create([
                'futgo_id'             => $futgoId,
                'validated_user_id'    => $userId,
                'validated_by_user_id' => $arbitro->id,
                'tournament_id'        => $copa->id,
                'result'               => $result,
                'method'               => $method,
            ]);
        }
    }

    /** Usuarios registrados (con cuenta) de un club, excluyendo nulos. */
    private function registeredMembers(string $slug, int $limit): \Illuminate\Support\Collection
    {
        $club = Club::where('slug', $slug)->first();
        if (! $club) {
            return collect();
        }

        return $club->players()
            ->whereNotNull('user_id')
            ->with('user')
            ->take($limit + 2)
            ->get()
            ->map(fn ($cp) => $cp->user)
            ->filter()
            ->values()
            ->take($limit);
    }
}
