<?php

namespace App\Notifications;

use App\Models\Game;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PredictionReminderNotification extends Notification
{
    public function __construct(public readonly Game $game) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = explode(' ', trim($notifiable->name))[0] ?: $notifiable->name;
        $home = trim(($this->game->home_flag ?? '') . ' ' . $this->game->home_team);
        $away = trim(($this->game->away_flag ?? '') . ' ' . $this->game->away_team);
        $when = $this->game->match_datetime->locale('es')->isoFormat('dddd D [de] MMMM [a las] HH:mm');

        return (new MailMessage)
            ->subject('⚽ Soy Pachón Mundial — Faltan 15 minutos para cerrar tu pronóstico')
            ->greeting("¡Hola {$firstName}! ⚽")
            ->line("Faltan **15 minutos** para que se cierre tu pronóstico del siguiente partido:")
            ->line("🏟️ **{$home}** vs **{$away}**")
            ->line("🕐 {$when} (hora Colombia GMT-5)")
            ->line('Si no ingresás tu pronóstico antes del cierre automático, este partido sumará **0 puntos** en tu ranking.')
            ->action('🎯 Ir a Mis Pronósticos', route('predictions.index'))
            ->line('¡Buena suerte!')
            ->salutation('— Equipo Soy Pachón Mundial');
    }
}
