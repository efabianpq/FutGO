<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Prediction;
use Illuminate\Console\Command;

class LockPredictions extends Command
{
    protected $signature = 'predictions:lock';

    protected $description = 'Bloquea pronósticos de partidos cuyo lock_datetime ya pasó (match_datetime - 5 min)';

    public function handle(): int
    {
        $now = now();

        $games = Game::where('lock_datetime', '<=', $now)
            ->where('status', '!=', 'finished')
            ->get();

        $matchesTouched = 0;
        $predictionsLocked = 0;

        foreach ($games as $game) {
            if ($game->status === 'upcoming') {
                $game->update(['status' => 'live']);
                $matchesTouched++;
            }

            $count = Prediction::where('match_id', $game->id)
                ->where('is_locked', false)
                ->update(['is_locked' => true, 'updated_at' => $now]);

            $predictionsLocked += $count;
        }

        $this->info("Partidos pasados a 'live': {$matchesTouched}");
        $this->info("Pronósticos bloqueados: {$predictionsLocked}");

        return self::SUCCESS;
    }
}
