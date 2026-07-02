<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Centro de Privacidad · Representación PRIVADA del propio usuario (o admin).
 *
 * SCAFFOLDING documentado para una futura API. Solo debe servirse al propio
 * usuario autenticado o a un admin (nunca en endpoints públicos). Aun así NUNCA
 * incluye password/tokens. El documento se marca como presente, no se expone en
 * claro salvo necesidad explícita.
 *
 * @property \App\Models\User $resource
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $u = $this->resource;

        return [
            'id'               => $u->id,
            'futgo_id'         => $u->futgo_id,
            'nombre'           => $u->name,
            'email'            => $u->email,
            'telefono'         => $u->phone_whatsapp,
            'tiene_documento'  => ! empty($u->document),
            'fecha_nacimiento' => $u->birthdate?->toDateString(),
            'ciudad'           => $u->city,
            'nivel'            => $u->play_level,
            'rol'              => $u->role,
            // NUNCA: password, remember_token, document_hash, tokens.
        ];
    }
}
