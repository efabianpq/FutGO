<?php

namespace App\Services\Privacy;

use App\Models\Privacy\LegalDocument;
use App\Models\Privacy\PrivacySetting;
use App\Models\Privacy\UserConsent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Centro de Privacidad · Orquesta el consentimiento (Ley 1581/2012).
 *
 * Cada aceptación queda registrada con versión + IP + user_agent como prueba
 * legal. El cache users.current_{privacy,terms}_version evita joins al detectar
 * si hace falta re-consentir (ver EnsureConsentUpToDate).
 */
class ConsentService
{
    /**
     * Registra el consentimiento inicial del registro y crea la configuración de
     * privacidad con defaults. Se llama dentro de la transacción del registro.
     */
    public function recordRegistration(User $user, Request $request, bool $marketing = false): void
    {
        foreach (LegalDocument::REQUIRED_AT_REGISTRATION as $type) {
            $this->record($user, $type, $request, 'register');
        }

        if ($marketing) {
            $this->record($user, 'marketing', $request, 'register');
        }

        $user->privacySetting()->firstOrCreate(
            ['user_id' => $user->id],
            PrivacySetting::defaults()
        );

        $this->refreshVersionCache($user);
    }

    /**
     * Re-aceptación de uno o varios documentos (cambio de versión). Actualiza el
     * cache de versiones del usuario.
     *
     * @param  string[]  $types
     */
    public function reconsent(User $user, Request $request, array $types): void
    {
        foreach ($types as $type) {
            $this->record($user, $type, $request, 'reconsent');
        }

        $this->refreshVersionCache($user);
    }

    /** Alta o baja del consentimiento de comunicaciones comerciales. */
    public function updateMarketing(User $user, bool $accepted, Request $request): void
    {
        UserConsent::create([
            'user_id'          => $user->id,
            'document_type'    => 'marketing',
            'document_version' => null,
            'accepted'         => $accepted,
            'accepted_at'      => now(),
            'ip'               => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'source'           => 'settings',
        ]);
    }

    /**
     * URL firmada de baja de comunicaciones, para incluir en el pie de los emails
     * comerciales. Sin expiración (el enlace debe seguir sirviendo con el tiempo).
     */
    public static function unsubscribeUrl(User $user): string
    {
        return \Illuminate\Support\Facades\URL::signedRoute('comunicaciones.baja', ['user' => $user->id]);
    }

    /** ¿El usuario aceptó actualmente las comunicaciones comerciales? */
    public function hasMarketingConsent(User $user): bool
    {
        $last = $user->consents()->ofType('marketing')->latestFirst()->first();

        return $last?->accepted === true;
    }

    /** Historial de consentimientos por tipo (para el Centro de Privacidad). */
    public function history(User $user)
    {
        return $user->consents()->latestFirst()->get()->groupBy('document_type');
    }

    /**
     * ¿El usuario tiene pendiente re-aceptar algún documento obligatorio porque se
     * publicó una versión nueva desde su última aceptación?
     *
     * @return string[]  tipos pendientes
     */
    public function outdatedRequiredTypes(User $user): array
    {
        $pending = [];

        $map = [
            'privacy' => $user->current_privacy_version,
            'terms'   => $user->current_terms_version,
        ];

        foreach ($map as $type => $acceptedVersion) {
            $current = LegalDocument::currentFor($type);

            if ($current !== null && $current->version !== $acceptedVersion) {
                $pending[] = $type;
            }
        }

        return $pending;
    }

    private function record(User $user, string $type, Request $request, string $source): void
    {
        $version = LegalDocument::currentFor($type)?->version;

        UserConsent::create([
            'user_id'          => $user->id,
            'document_type'    => $type,
            'document_version' => $version,
            'accepted'         => true,
            'accepted_at'      => now(),
            'ip'               => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'source'           => $source,
        ]);
    }

    private function refreshVersionCache(User $user): void
    {
        $user->forceFill([
            'current_privacy_version' => LegalDocument::currentFor('privacy')?->version,
            'current_terms_version'   => LegalDocument::currentFor('terms')?->version,
        ])->save();
    }
}
