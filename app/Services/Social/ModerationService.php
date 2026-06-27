<?php

namespace App\Services\Social;

use App\Models\Social\ContentReport;
use App\Models\Social\Message;
use App\Models\Social\Opportunity;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * FutGO Social — S1-F · Moderación mínima viable.
 *
 * Resuelve content_reports con tres acciones posibles:
 *   - dismissed: el reporte era infundado, sin consecuencias.
 *   - hidden:    el contenido (Opportunity o Message) queda oculto del listado.
 *   - suspended: el usuario reportado queda suspendido (sin borrar su cuenta).
 *
 * Toda acción deja trazabilidad: quién, cuándo, qué decidió y por qué.
 */
class ModerationService
{
    /**
     * Resuelve un content_report. $action puede ser 'dismissed', 'hidden' o
     * 'suspended'. $notes es la justificación interna del admin.
     * Si $action==='suspended', $suspendDays controla la duración (null=indefinida).
     */
    public function resolveReport(
        ContentReport $report,
        User $admin,
        string $action,
        ?string $notes = null,
        ?int $suspendDays = null,
        ?string $suspendReason = null,
    ): ContentReport {
        if (! in_array($action, ContentReport::ACTIONS, true)) {
            throw new \InvalidArgumentException("Acción de moderación inválida: {$action}");
        }

        match ($action) {
            ContentReport::ACTION_HIDDEN    => $this->hideReportable($report),
            ContentReport::ACTION_SUSPENDED => $this->suspendReportedUser($report, $suspendDays, $suspendReason),
            default => null,  // dismissed: sin efecto sobre la entidad
        };

        $report->update([
            'status'            => ContentReport::STATUS_RESUELTO,
            'resolution_action' => $action,
            'admin_notes'       => $notes,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at'       => now(),
        ]);

        return $report->refresh();
    }

    /**
     * Oculta la entidad reportada sin borrarla. Solo aplica sobre Opportunity
     * y Message. Para User/Club el admin actúa con suspendUser directamente.
     */
    public function hideEntity(Opportunity|Message $entity): void
    {
        $entity->update(['is_hidden' => true]);
    }

    /** Revierte el ocultamiento. */
    public function unhideEntity(Opportunity|Message $entity): void
    {
        $entity->update(['is_hidden' => false]);
    }

    /**
     * Suspende un usuario. Con $days=null la suspensión es indefinida (admin la
     * levanta manualmente). Idempotente: rellamar sólo actualiza los parámetros.
     */
    public function suspendUser(User $user, ?int $days = null, ?string $reason = null): void
    {
        $user->update([
            'is_suspended'     => true,
            'suspended_until'  => $days ? Carbon::now()->addDays($days) : null,
            'suspended_reason' => $reason,
        ]);
    }

    /** Levanta la suspensión de un usuario. */
    public function unsuspendUser(User $user): void
    {
        $user->update([
            'is_suspended'     => false,
            'suspended_until'  => null,
            'suspended_reason' => null,
        ]);
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    /** Oculta el contenido reportado si es Opportunity o Message. */
    private function hideReportable(ContentReport $report): void
    {
        $entity = $report->reportable;

        if ($entity instanceof Opportunity || $entity instanceof Message) {
            $entity->update(['is_hidden' => true]);
        }
    }

    /** Suspende al usuario dueño del contenido reportado. */
    private function suspendReportedUser(ContentReport $report, ?int $days, ?string $reason): void
    {
        $entity = $report->reportable;

        $user = match (true) {
            $entity instanceof User        => $entity,
            $entity instanceof Opportunity => $entity->user,
            $entity instanceof Message     => $entity->sender_user_id
                ? User::find($entity->sender_user_id)
                : null,
            default => null,
        };

        if ($user) {
            $this->suspendUser($user, $days, $reason);
        }
    }
}
