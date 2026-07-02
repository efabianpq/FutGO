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
            ->subject("¡Bienvenido a FutGO, {$firstName}!")
            ->greeting("¡Hola {$firstName}, bienvenido a FutGO!")
            ->line('Tu cuenta ya está lista para usarse.')
            ->line('Ya podés crear o inscribirte en torneos, armar tu equipo y conectar con la comunidad de fútbol amateur.')
            ->action('Ir a FutGO', url('/dashboard'))
            ->salutation('— El equipo de FutGO');
    }
}
