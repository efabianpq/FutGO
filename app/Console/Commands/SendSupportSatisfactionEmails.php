<?php

namespace App\Console\Commands;

use App\Models\Support\SupportTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSupportSatisfactionEmails extends Command
{
    protected $signature = 'support:send-satisfaction-emails';

    protected $description = 'Envía emails de satisfacción a tickets resueltos pendientes';

    public function handle(): void
    {
        $tickets = SupportTicket::where('status', 'resuelto')
            ->whereNull('satisfaction_sent_at')
            ->whereNull('satisfaction_response')
            ->where('resolved_at', '<=', now()->subHours(2))
            ->with('user')
            ->get();

        foreach ($tickets as $ticket) {
            if (! $ticket->user || ! $ticket->user->email) {
                continue;
            }

            $positiveUrl = route('soporte.satisfaction', $ticket) . '?response=positiva';
            $negativeUrl = route('soporte.satisfaction', $ticket) . '?response=negativa';

            Mail::raw(
                "Hola {$ticket->user->name} 👋\n\n"
                . "Resolvimos tu consulta: \"{$ticket->subject}\"\n\n"
                . "¿Se resolvió tu problema?\n\n"
                . "👍 Sí, gracias: {$positiveUrl}\n\n"
                . "👎 No, todavía tengo el problema: {$negativeUrl}\n\n"
                . '— Equipo FutGO',
                fn ($m) => $m
                    ->to($ticket->user->email)
                    ->subject('FutGO Soporte — ¿Se resolvió tu consulta?')
            );

            $ticket->update(['satisfaction_sent_at' => now()]);
            $this->info("Email enviado para ticket #{$ticket->id}");
        }

        $this->info("Procesados {$tickets->count()} tickets.");
    }
}
