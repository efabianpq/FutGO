<?php

namespace App\Console\Commands;

use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentMatchNotification;
use App\Notifications\MatchReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recordatorios de partidos próximos del módulo TORNEOS (Sesión G).
 *
 * Envía un email a los jugadores CONVOCADOS (status convocado/confirmado) de los
 * partidos programados dentro de la ventana indicada. Reutiliza el patrón de la
 * polla: idempotencia vía tournament_match_notifications (no reenvía) y respeta la
 * preferencia notifications_enabled del usuario.
 */
class SendMatchReminders extends Command
{
    protected $signature = 'torneos:match-reminders {--minutes=1440 : Ventana hacia adelante en minutos (default 24h)}';

    protected $description = 'Envía recordatorios por email a jugadores convocados de partidos próximos del módulo torneos.';

    public function handle(): int
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes((int) $this->option('minutes'));

        $matches = TournamentMatch::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$now, $windowEnd])
            ->with(['homeTeam', 'awayTeam', 'phase.tournament'])
            ->orderBy('scheduled_at')
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No hay partidos próximos en la ventana indicada. Listo.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($matches as $match) {
            // Jugadores convocados o confirmados, vinculados a una cuenta con notificaciones ON.
            $callUps = MatchCallUp::where('match_id', $match->id)
                ->whereIn('status', ['convocado', 'confirmado'])
                ->whereHas('teamPlayer.user', fn ($q) => $q->where('notifications_enabled', true))
                ->with('teamPlayer.user')
                ->get();

            $this->line("Partido #{$match->match_number} — convocados notificables: {$callUps->count()}");

            foreach ($callUps as $callUp) {
                $user = $callUp->teamPlayer?->user;
                if (! $user) {
                    continue;
                }

                DB::transaction(function () use ($user, $match, &$sent, &$skipped) {
                    try {
                        // Insert primero (unique) → solo notifica si la inserción tuvo éxito.
                        TournamentMatchNotification::create([
                            'user_id'  => $user->id,
                            'match_id' => $match->id,
                            'type'     => TournamentMatchNotification::TYPE_REMINDER,
                            'sent_at'  => now(),
                        ]);
                    } catch (\Throwable $e) {
                        $skipped++;   // ya notificado (idempotencia)
                        return;
                    }

                    $user->notify(new MatchReminderNotification($match));
                    $sent++;
                });
            }
        }

        $this->info("Recordatorios enviados: {$sent}");
        if ($skipped > 0) {
            $this->line("Duplicados saltados: {$skipped}");
        }

        return self::SUCCESS;
    }
}
