<?php

namespace App\Services\Privacy;

use App\Models\Privacy\UserConsent;
use App\Models\User;
use App\Notifications\Privacy\GuardianConsentNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Centro de Privacidad · Consentimiento parental de menores (Decreto 1377/2013).
 *
 * Un menor de 18 se registra con el correo de su representante legal. Hasta que
 * el representante confirma (enlace firmado), la cuenta queda con capacidades
 * limitadas (pending_guardian_consent=true). Todo detrás del flag
 * config('privacy.parental_consent').
 */
class ParentalConsentService
{
    /** Envía al representante el enlace firmado de confirmación. */
    public function sendRequest(User $user): void
    {
        if (empty($user->guardian_email)) {
            return;
        }

        $url = $this->confirmationUrl($user);

        Notification::route('mail', $user->guardian_email)
            ->notify(new GuardianConsentNotification($user, $url));
    }

    /** URL firmada (válida 14 días) para que el representante confirme. */
    public function confirmationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'parental.confirm',
            now()->addDays(14),
            ['user' => $user->id]
        );
    }

    /** Marca la cuenta como confirmada por el representante y registra el consentimiento. */
    public function confirm(User $user): void
    {
        if (! $user->pending_guardian_consent) {
            return;
        }

        UserConsent::create([
            'user_id'          => $user->id,
            'document_type'    => 'parental',
            'document_version' => null,
            'accepted'         => true,
            'accepted_at'      => now(),
            'ip'               => request()?->ip(),
            'user_agent'       => request()?->userAgent(),
            'source'           => 'parental',
        ]);

        $user->forceFill(['pending_guardian_consent' => false])->save();
    }
}
