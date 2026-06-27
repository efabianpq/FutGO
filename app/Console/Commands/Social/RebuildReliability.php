<?php

namespace App\Console\Commands\Social;

use App\Services\Social\ReliabilityService;
use Illuminate\Console\Command;

/**
 * FutGO Social — reconstruye el score de confiabilidad para todos los
 * actores (users y clubs) que tienen al menos un reliability_event.
 *
 * Idempotente. Corre por el scheduler (diario). Coexiste con los schedulers
 * de torneos y polla — no los modifica.
 */
class RebuildReliability extends Command
{
    protected $signature = 'social:rebuild-reliability';

    protected $description = 'Reconstruye el score de confiabilidad para todos los usuarios y clubs.';

    public function handle(ReliabilityService $reliability): int
    {
        $this->info('Reconstruyendo scores de confiabilidad…');

        $reliability->rebuild();

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
