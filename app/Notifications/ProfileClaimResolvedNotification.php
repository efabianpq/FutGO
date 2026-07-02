<?php

namespace App\Notifications;

use App\Models\Torneos\ProfileClaim;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al RECLAMANTE del resultado de su reclamo de perfil (aprobado o rechazado).
 */
class ProfileClaimResolvedNotification extends Notification
{
    public function __construct(public ProfileClaim $claim, public bool $approved)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable->notifications_enabled === false ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clubName = $this->claim->club?->name ?? 'el equipo';

        if ($this->approved) {
            return (new MailMessage)
                ->subject("¡Tu perfil de {$clubName} quedó vinculado!")
                ->greeting('¡Buenas noticias!')
                ->line("Tu reclamo fue aprobado: ahora tu cuenta está vinculada a tu registro en **{$clubName}**.")
                ->line('Heredaste tu historial deportivo (partidos, goles, tarjetas) y ya tienes tu credencial digital con QR.')
                ->action('Ver mi carrera', url('/torneos/mi-carrera'));
        }

        $note = $this->claim->resolution_note;

        return (new MailMessage)
            ->subject("Tu reclamo en {$clubName} fue rechazado")
            ->greeting('Hola')
            ->line("Tu reclamo para vincularte al registro de **{$clubName}** fue rechazado.")
            ->lineIf((bool) $note, "Motivo: {$note}")
            ->line('Si crees que es un error, contacta al capitán del equipo.')
            ->action('Ver mis reclamos', url('/torneos/reclamos'));
    }
}
