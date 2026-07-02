<?php

namespace App\Notifications\Privacy;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Centro de Privacidad · Solicitud de consentimiento al representante legal.
 */
class GuardianConsentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private User $minor, private string $url)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirma el registro de un menor en FutGO')
            ->greeting('Consentimiento del representante legal')
            ->line("{$this->minor->name} se registró en FutGO e indicó este correo como el de su representante legal.")
            ->line('Como FutGO trata datos de menores, necesitamos tu autorización para activar plenamente la cuenta.')
            ->action('Confirmar y autorizar', $this->url)
            ->line('Si no reconoces este registro, ignora este correo y la cuenta quedará limitada.')
            ->salutation('— El equipo de FutGO');
    }
}
