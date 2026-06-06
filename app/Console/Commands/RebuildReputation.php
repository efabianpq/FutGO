<?php

namespace App\Console\Commands;

use App\Services\Torneos\ReputationService;
use Illuminate\Console\Command;

/**
 * Reconstruye toda la reputación FUTGO (acumulados, fair play, logros, ranking).
 * Pensado para correr periódicamente por cron — el ranking no se calcula por request.
 */
class RebuildReputation extends Command
{
    protected $signature = 'torneos:rebuild-reputation';

    protected $description = 'Recalcula acumulados, fair play, logros y ranking FUTGO (cache).';

    public function handle(ReputationService $reputation): int
    {
        $this->info('Reconstruyendo reputación FUTGO...');
        $reputation->rebuildAll();
        $this->info('Listo: acumulados, fair play, logros y ranking actualizados.');

        return self::SUCCESS;
    }
}
