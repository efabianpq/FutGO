<?php

namespace App\Notifications;

use App\Models\Torneos\TournamentMatch;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Recordatorio de partido próximo para jugadores convocados (módulo Torneos, Sesión G).
 */
class MatchReminderNotification extends Notification
{
    public function __construct(public readonly TournamentMatch $match) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = explode(' ', trim($notifiable->name))[0] ?: $notifiable->name;
        $home = $this->match->homeTeam?->name ?? 'Por definir';
        $away = $this->match->awayTeam?->name ?? 'Por definir';
        $tournament = $this->match->phase?->tournament;

        $when = $this->match->scheduled_at
            ? $this->match->scheduled_at->locale('es')->isoFormat('dddd D [de] MMMM [a las] HH:mm')
            : 'fecha por confirmar';

        $mail = (new MailMessage)
            ->subject('⚽ FutGO — Tienes un partido próximo')
            ->greeting("¡Hola {$firstName}! ⚽")
            ->line('Fuiste convocado para un partido próximo:')
            ->line("🏟️ **{$home}** vs **{$away}**")
            ->line("🕐 {$when}");

        if ($this->match->venue) {
            $mail->line("📍 Cancha: {$this->match->venue}");
        }

        if ($tournament) {
            $mail->line("🏆 {$tournament->name}")
                 ->action('Ver mi carrera', route('torneos.mi-carrera'));
        }

        return $mail
            ->line('Confirma tu asistencia con tu capitán y no olvides tu credencial FUTGO.')
            ->salutation('— Equipo FutGO');
    }
}
