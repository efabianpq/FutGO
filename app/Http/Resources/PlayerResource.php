<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Centro de Privacidad · Representación PÚBLICA de un jugador para una futura API.
 *
 * SCAFFOLDING documentado (aún no hay API pública; la app es WebView). Cuando se
 * exponga una API, todo endpoint debe devolver este resource — NUNCA el modelo
 * User crudo — para garantizar que email/teléfono/documento/IP/tokens jamás
 * salgan, y para respetar privacy_settings (ver DataClassification).
 *
 * @property \App\Models\User $resource
 */
class PlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $u = $this->resource;
        $p = $u->privacySetting;

        return [
            'futgo_id' => $u->futgo_id,
            'nombre'   => $u->name,
            'avatar'   => ($p?->show_photo ?? true) ? $u->avatar_url : null,
            'ciudad'   => ($p?->show_city ?? true) ? $u->city : null,
            'nivel'    => $u->play_level,
            // NUNCA: email, phone_whatsapp, document, birthdate, ip, tokens.
        ];
    }
}
