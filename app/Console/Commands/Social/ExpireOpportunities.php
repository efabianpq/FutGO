<?php

namespace App\Console\Commands\Social;

use App\Services\Social\OpportunityService;
use Illuminate\Console\Command;

/**
 * FutGO Social — vence automáticamente las oportunidades cuya ventana ya pasó.
 *
 * Corre por el scheduler (cada hora). Las oportunidades vencidas dejan de
 * aparecer en el listado activo. Reconstruible/idempotente: re-ejecutar no
 * tiene efecto sobre las ya vencidas.
 */
class ExpireOpportunities extends Command
{
    protected $signature = 'social:expire-opportunities';

    protected $description = 'Marca como vencidas las oportunidades cuya vigencia ya pasó.';

    public function handle(OpportunityService $opportunities): int
    {
        $count = $opportunities->expireDue();

        $this->info("Oportunidades vencidas: {$count}.");

        return self::SUCCESS;
    }
}
