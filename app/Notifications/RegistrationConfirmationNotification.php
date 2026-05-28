<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationConfirmationNotification extends Notification
{
    public function __construct(private User $user) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = explode(' ', trim($notifiable->name))[0] ?: $notifiable->name;

        return (new MailMessage)
            ->subject("Recibimos tu registro, {$firstName} — completá estos 3 pasos para activarte")
            ->greeting("¡Hola {$firstName}, gracias por registrarte en @SoyPachon!")
            ->line('Tu cuenta fue creada correctamente. Para activarla y empezar a pronosticar, completá los siguientes 3 pasos:')
            ->line('---')
            ->line('**1. Hacé la transferencia**')
            ->line('Transferí **$30.000 COP** al número **3013966515** (Nequi o Daviplata). Este es el número del administrador de la plataforma.')
            ->line('---')
            ->line('**2. Enviá el comprobante por WhatsApp**')
            ->line("Mandá el comprobante de pago por WhatsApp al mismo número: **3013966515**. Incluí tu nombre completo y el correo con el que te registraste ({$notifiable->email}).")
            ->line('---')
            ->line('**3. Esperá tu código de activación**')
            ->line('Una vez verificado tu pago, recibirás un código de activación por WhatsApp. Iniciá sesión en la plataforma e ingresá el código.')
            ->action('Iniciar sesión', url('/login'))
            ->line('Si ya completaste el pago y aún no recibiste tu código, escribinos directamente al WhatsApp **3013966515**.')
            ->salutation('— Equipo @SoyPachon Mundial');
    }
}
