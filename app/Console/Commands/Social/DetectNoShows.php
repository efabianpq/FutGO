<?php

namespace App\Console\Commands\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\Social\ReliabilityEvent;
use App\Models\Torneos\Club;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FutGO Social — detecta amistosos vencidos sin resultado y genera un
 * reliability_event de tipo no_show para AMBOS clubs participantes.
 *
 * Criterio: amistoso en estado `confirmado` cuya scheduled_at ya pasó y
 * ninguno de los dos equipos cargó marcador. Idempotente: no repite el evento
 * si ya existe uno de no_show con ese friendly_match_id para el mismo club.
 *
 * Corre por el scheduler (cada hora) junto a social:expire-opportunities.
 */
class DetectNoShows extends Command
{
    protected $signature = 'social:detect-no-shows';

    protected $description = 'Detecta amistosos pasados sin resultado y registra no_show para ambos clubs.';

    public function handle(): int
    {
        $overdue = FriendlyMatch::where('status', FriendlyMatch::STATUS_CONFIRMADO)
            ->where('scheduled_at', '<', now())
            ->whereNull('home_reported_at')
            ->whereNull('away_reported_at')
            ->get();

        $recorded = 0;

        foreach ($overdue as $fm) {
            DB::transaction(function () use ($fm, &$recorded) {
                foreach ([$fm->home_club_id, $fm->away_club_id] as $clubId) {
                    $alreadyExists = ReliabilityEvent::where('subject_type', 'club')
                        ->where('subject_id', $clubId)
                        ->where('type', ReliabilityEvent::TYPE_NO_SHOW)
                        ->where('friendly_match_id', $fm->id)
                        ->exists();

                    if ($alreadyExists) {
                        continue;
                    }

                    ReliabilityEvent::create([
                        'subject_type'     => 'club',
                        'subject_id'       => $clubId,
                        'type'             => ReliabilityEvent::TYPE_NO_SHOW,
                        'friendly_match_id' => $fm->id,
                        'occurred_at'      => $fm->scheduled_at ?? now(),
                    ]);

                    $recorded++;
                }

                // Marca el amistoso como cancelado para que no siga
                // apareciendo en el listado activo y no se detecte en futuras ejecuciones.
                $fm->update(['status' => FriendlyMatch::STATUS_CANCELADO]);
            });
        }

        $this->info("No-shows detectados y registrados: {$recorded} (de {$overdue->count()} amistosos).");

        return self::SUCCESS;
    }
}
