<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\MatchNotification;
use App\Models\User;
use App\Notifications\PredictionReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendReminderNotifications extends Command
{
    protected $signature = 'notifications:reminders';

    protected $description = 'Envía un recordatorio por email a usuarios activos sin pronóstico, 15 min antes del cierre de cada partido.';

    public function handle(): int
    {
        $now = now();
        $windowEnd = $now->copy()->addMinutes(15);

        // Partidos cuyo cierre está dentro de los próximos 15 min y todavía no inició
        $games = Game::where('status', 'upcoming')
            ->whereBetween('lock_datetime', [$now, $windowEnd])
            ->orderBy('lock_datetime')
            ->get();

        if ($games->isEmpty()) {
            $this->info("No hay partidos en ventana de aviso (próximos 15 min). Listo.");
            return self::SUCCESS;
        }

        $sentTotal = 0;
        $skippedTotal = 0;

        foreach ($games as $game) {
            // Usuarios activos con notificaciones ON
            // que NO tengan pronóstico para este partido
            // y a quienes NO se les haya enviado ya el recordatorio
            $candidates = User::query()
                ->where('is_active', true)
                ->where('notifications_enabled', true)
                ->whereNotExists(function ($q) use ($game) {
                    $q->from('predictions')
                      ->whereColumn('predictions.user_id', 'users.id')
                      ->where('predictions.match_id', $game->id);
                })
                ->whereNotExists(function ($q) use ($game) {
                    $q->from('match_notifications')
                      ->whereColumn('match_notifications.user_id', 'users.id')
                      ->where('match_notifications.match_id', $game->id)
                      ->where('match_notifications.type', MatchNotification::TYPE_REMINDER);
                })
                ->get(['id', 'name', 'email']);

            $this->line("Partido #{$game->match_number} ({$game->home_team} vs {$game->away_team}) — cierre {$game->lock_datetime->format('H:i')} — candidatos: " . $candidates->count());

            foreach ($candidates as $user) {
                DB::transaction(function () use ($user, $game, &$sentTotal, &$skippedTotal) {
                    // Insert primero (con unique constraint) y solo envía si la inserción tuvo éxito
                    try {
                        MatchNotification::create([
                            'user_id' => $user->id,
                            'match_id' => $game->id,
                            'type' => MatchNotification::TYPE_REMINDER,
                            'sent_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        // race condition o duplicado — saltar
                        $skippedTotal++;
                        return;
                    }

                    $user->notify(new PredictionReminderNotification($game));
                    $sentTotal++;
                });
            }
        }

        $this->info("");
        $this->info("Recordatorios enviados: {$sentTotal}");
        if ($skippedTotal > 0) {
            $this->line("Duplicados saltados:   {$skippedTotal}");
        }

        return self::SUCCESS;
    }
}
