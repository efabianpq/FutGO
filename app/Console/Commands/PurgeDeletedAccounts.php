<?php

namespace App\Console\Commands;

use App\Services\Privacy\AccountDeletionService;
use Illuminate\Console\Command;

/**
 * Centro de Privacidad · Ejecuta las eliminaciones de cuenta cuyo periodo de
 * gracia venció (data_requests type=delete, status=processing, executes_at<=now).
 *
 * La ejecución ANONIMIZA (no borra la fila): buena parte del esquema tiene FKs
 * cascadeOnDelete hacia users, y un DELETE real arrastraría torneos y estadísticas
 * históricas de otros jugadores. Ver AccountDeletionService::anonymize().
 * Programado diariamente en routes/console.php.
 */
class PurgeDeletedAccounts extends Command
{
    protected $signature = 'futgo:purge-deleted-accounts';

    protected $description = 'Ejecuta las eliminaciones de cuenta con periodo de gracia vencido (anonimización).';

    public function handle(AccountDeletionService $deletion): int
    {
        $due = $deletion->dueForExecution();

        if ($due->isEmpty()) {
            $this->info('No hay cuentas con periodo de gracia vencido.');

            return self::SUCCESS;
        }

        foreach ($due as $request) {
            $deletion->execute($request);
            $this->line("Cuenta #{$request->user_id} anonimizada (solicitud #{$request->id}).");
        }

        $this->info("{$due->count()} cuenta(s) anonimizada(s).");

        return self::SUCCESS;
    }
}
