<?php

namespace App\Notifications;

use App\Support\Settings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = explode(' ', trim($notifiable->name))[0] ?: $notifiable->name;
        $prizePool = Settings::prizePool();
        $poolText = $prizePool !== null
            ? '$' . number_format($prizePool, 0, ',', '.') . ' COP'
            : 'en crecimiento';

        return (new MailMessage)
            ->subject("¡Ya estás adentro, {$firstName}! @SoyPachon")
            ->greeting("¡Hola {$firstName}, bienvenido al juego!")
            ->line('Tu cuenta está activa. Ya podés ingresar tus pronósticos para los **104 partidos del Mundial FIFA 2026**.')
            ->line('**Recordá las reglas clave:**')
            ->line('🏆 **5 puntos** — Ambos marcadores exactos')
            ->line('✅ **3 puntos** — Ganador correcto + un marcador exacto')
            ->line('👍 **2 puntos** — Solo ganador correcto')
            ->line('⚡ **1 punto** — Ganador incorrecto pero un marcador exacto')
            ->line('❌ **0 puntos** — Todo lo demás')
            ->line('Los pronósticos se cierran **5 minutos antes** de cada partido.')
            ->action('Ver Mis Pronósticos', url('/predictions'))
            ->line("El pozo actual es de **{$poolText}**. El top 3 se lleva el 60%, 25% y 15%.")
            ->salutation('— Equipo @SoyPachon Mundial');
    }
}
