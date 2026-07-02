<?php

namespace App\Services\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Analiza el mensaje del usuario y verifica el estado del sistema para ese usuario
 * específico ANTES de responder. El usuario nunca ve los checks crudos, solo la
 * conclusión.
 */
class SilentDiagnosticService
{
    public function diagnose(User $user, string $userMessage): array
    {
        $message = mb_strtolower($userMessage);
        $checks  = [];
        $issues  = [];

        // FIXTURE
        if ($this->mentions($message, ['fixture', 'calendario', 'partidos no aparecen', 'cruces'])) {
            $torneos = $user->teamPlayers()
                ->with(['team.tournament'])
                ->get()
                ->map(fn ($tp) => $tp->team?->tournament)
                ->filter()->unique('id');

            foreach ($torneos as $torneo) {
                if ($torneo->status === 'open') {
                    $checks[] = "Torneo '{$torneo->name}' está en estado 'open' — fixture aún no generado.";
                    $issues[] = "El fixture del torneo '{$torneo->name}' no existe porque el torneo todavía está en etapa de inscripción (open). El organizador debe generarlo desde el panel de administración.";
                } elseif ($torneo->status === 'draft') {
                    $issues[] = "El torneo '{$torneo->name}' está en borrador — aún no está publicado ni tiene fixture.";
                } else {
                    $checks[] = "Torneo '{$torneo->name}': {$torneo->status} ✓";
                }
            }
        }

        // RESULTADO
        if ($this->mentions($message, ['resultado', 'cargar resultado', 'guardar resultado', 'no me deja', 'ingresar resultado'])) {
            $torneos = $user->captainClubs()
                ->with(['teams.tournament'])
                ->get()
                ->flatMap(fn ($c) => $c->teams)
                ->map(fn ($t) => $t->tournament)
                ->filter()->unique('id');

            foreach ($torneos as $torneo) {
                if ($torneo->status !== 'in_progress') {
                    $issues[] = "El torneo '{$torneo->name}' no está en juego (status: {$torneo->status}) — los resultados solo se pueden cargar cuando el torneo está activo.";
                } else {
                    $checks[] = "Torneo '{$torneo->name}': en juego ✓";
                }
            }
        }

        // RANKING
        if ($this->mentions($message, ['ranking', 'posición', 'puntaje', 'no actualiza'])) {
            $ultimoRanking = \App\Models\Torneos\FutgoRanking::latest('updated_at')->first();
            if (! $ultimoRanking || $ultimoRanking->updated_at->lt(now()->subHours(25))) {
                $issues[] = 'El ranking no se ha actualizado en las últimas 24 horas — se recalcula automáticamente al finalizar torneos.';
            } else {
                $checks[] = "Ranking actualizado: {$ultimoRanking->updated_at->diffForHumans()} ✓";
            }
        }

        // CREDENCIAL QR
        if ($this->mentions($message, ['credencial', 'qr', 'futgo id', 'fg-'])) {
            if (! $user->futgo_id) {
                $issues[] = 'El usuario no tiene futgo_id asignado — esto no debería ocurrir. Escalar.';
            } else {
                $checks[] = "FutGO ID: {$user->futgo_id} ✓";
            }
        }

        // CONVOCATORIA
        if ($this->mentions($message, ['convocatoria', 'no aparezco', 'no me convocaron', 'convocar'])) {
            $esCapitan = $user->captainClubs()->exists();
            $checks[] = $esCapitan
                ? 'Usuario es capitán ✓'
                : 'Usuario es jugador (no capitán) — solo los capitanes arman convocatorias.';
        }

        // SCHEDULER / NOTIFICACIONES
        if ($this->mentions($message, ['recordatorio', 'notificación', 'no me llegó', 'email', 'correo'])) {
            $ultimaNotif = \App\Models\Torneos\TournamentMatchNotification::latest()->first();
            if ($ultimaNotif && $ultimaNotif->created_at->gt(now()->subHours(2))) {
                $checks[] = 'Notificaciones: activas ✓';
            } else {
                $checks[] = 'No se encontraron notificaciones recientes — puede ser normal si no hay partidos próximos.';
            }
        }

        return [
            'checks'       => $checks,
            'issues_found' => ! empty($issues),
            'issues'       => $issues,
            'diagnosis'    => empty($issues) ? null : implode(' ', $issues),
        ];
    }

    private function mentions(string $message, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($message, $kw)) {
                return true;
            }
        }

        return false;
    }
}
