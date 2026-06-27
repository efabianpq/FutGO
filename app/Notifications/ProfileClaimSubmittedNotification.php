<?php

namespace App\Notifications;

use App\Models\Torneos\ProfileClaim;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a quien debe APROBAR un reclamo de perfil: el capitán del club (si el
 * reclamo está pendiente) o un admin de la plataforma (si nace/queda escalado).
 */
class ProfileClaimSubmittedNotification extends Notification
{
    public function __construct(public ProfileClaim $claim)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->notifications_enabled === false ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $claim      = $this->claim;
        $playerName = $claim->user?->name ?? 'Un jugador';
        $registro   = $claim->clubPlayer?->displayName() ?? $claim->document ?? 'un registro';
        $clubName   = $claim->club?->name ?? 'tu equipo';
        $escalated  = $claim->isEscalated();

        $url = $escalated
            ? url('/admin/torneos/reclamos')
            : url('/torneos/reclamos/aprobaciones');

        return (new MailMessage)
            ->subject($escalated
                ? "Reclamo de perfil escalado — {$clubName}"
                : "Un jugador quiere vincularse a {$clubName}")
            ->greeting('¡Hola!')
            ->line("{$playerName} quiere vincular su cuenta de FutGO al registro **{$registro}** de **{$clubName}**.")
            ->line($escalated
                ? 'El equipo no tiene un capitán activo, así que el reclamo llegó a la plataforma para que lo resuelvas.'
                : 'Si lo aprobás, el jugador heredará el historial de ese registro (partidos, goles, tarjetas).')
            ->action('Revisar el reclamo', $url)
            ->line('Si no reconocés a esta persona, podés rechazar el reclamo: el registro queda sin cambios.');
    }
}
