<?php

namespace App\Services\Support;

use App\Models\Support\SupportIncidentPattern;
use App\Models\Support\SupportTicket;
use Illuminate\Support\Facades\Mail;

class PatternDetectorService
{
    public function analyze(SupportTicket $ticket): void
    {
        // Generar clave de patrón: categoría + primeras palabras del subject
        $words      = collect(explode(' ', mb_strtolower($ticket->subject)))->take(3)->implode('_');
        $patternKey = $ticket->category . ':' . $words;

        $windowMinutes = config('support.pattern_window', 30);
        $minTickets    = config('support.pattern_min', 5);

        // Contar tickets con la misma categoría en la ventana de tiempo
        $count = SupportTicket::where('category', $ticket->category)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        $pattern = SupportIncidentPattern::firstOrCreate(
            ['pattern_key' => $patternKey],
            ['first_detected_at' => now(), 'tickets_count' => 0]
        );

        $pattern->increment('tickets_count');

        if ($count >= $minTickets && is_null($pattern->team_alerted_at) && ! $pattern->resolved) {
            $this->alertTeam($pattern, $ticket, $count);
            $pattern->update(['team_alerted_at' => now()]);
        }
    }

    private function alertTeam(SupportIncidentPattern $pattern, SupportTicket $ticket, int $count): void
    {
        $teamEmail = config('support.team_email');
        if (! $teamEmail) {
            return;
        }

        Mail::raw(
            "⚠️ PATRÓN DETECTADO EN FUTGO SOPORTE\n\n"
            . "Se recibieron {$count} tickets con categoría '{$ticket->category}' "
            . 'en los últimos ' . config('support.pattern_window') . " minutos.\n\n"
            . "Patrón: {$pattern->pattern_key}\n"
            . "Primer ticket: #{$ticket->id} — {$ticket->subject}\n\n"
            . 'Revisa el panel: ' . url('/admin/soporte'),
            fn ($m) => $m->to($teamEmail)->subject("⚠️ FutGO Soporte — Patrón detectado: {$ticket->category}")
        );
    }
}
