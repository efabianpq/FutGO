<?php

namespace App\Notifications\Privacy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Centro de Privacidad · Código de verificación para eliminar la cuenta.
 *
 * Es una comunicación transaccional (no comercial): confirma una acción crítica
 * solicitada por el propio usuario.
 */
class AccountDeletionCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirma la eliminación de tu cuenta FutGO')
            ->greeting('Solicitud de eliminación de cuenta')
            ->line('Recibimos una solicitud para eliminar tu cuenta de FutGO.')
            ->line('Tu código de confirmación es:')
            ->line("**{$this->code}**")
            ->line('Ingrésalo en la pantalla de eliminación para continuar. Si no fuiste tú, ignora este correo: tu cuenta no se eliminará sin este código.')
            ->salutation('— El equipo de FutGO');
    }
}
