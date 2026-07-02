<?php

namespace App\Services\Privacy;

use App\Models\User;

/**
 * Centro de Privacidad · Portabilidad de datos (Ley 1581/2012, derecho de consulta).
 *
 * Arma una copia estructurada de los datos del propio usuario. NUNCA incluye
 * datos de terceros (solo lo que le pertenece o le concierne directamente).
 */
class PrivacyExportService
{
    public function build(User $user): array
    {
        return [
            'exportado_el' => now()->toIso8601String(),
            'perfil' => [
                'futgo_id'        => $user->futgo_id,
                'nombre'          => $user->name,
                'email'           => $user->email,
                'telefono'        => $user->phone_whatsapp,
                'documento'       => $user->document,
                'fecha_nacimiento' => $user->birthdate?->toDateString(),
                'ciudad'          => $user->city,
                'nivel'           => $user->play_level,
                'creado_el'       => $user->created_at?->toIso8601String(),
            ],
            'configuracion_privacidad' => $user->privacy()->only([
                'show_email', 'show_phone', 'show_birthdate', 'show_city', 'show_photo',
                'show_stats', 'show_teams', 'show_history', 'public_profile', 'searchable',
                'indexable_by_search_engines', 'allow_messages',
            ]),
            'consentimientos' => $user->consents()->latestFirst()->get()->map(fn ($c) => [
                'documento' => $c->document_type,
                'version'   => $c->document_version,
                'aceptado'  => $c->accepted,
                'fecha'     => $c->accepted_at?->toIso8601String(),
                'origen'    => $c->source,
            ])->all(),
            'estadisticas_carrera' => optional($user->careerStat)->only([
                'matches_played', 'goals', 'assists', 'yellow_cards', 'red_cards',
                'wins', 'draws', 'losses', 'clean_sheets', 'mvp_count',
            ]) ?? [],
            'logros' => $user->achievements()->get()->map(fn ($a) => [
                'nombre'      => $a->name ?? $a->code ?? null,
                'obtenido_el' => $a->pivot->awarded_at ?? null,
            ])->all(),
            'oportunidades_publicadas' => $user->opportunities()->get()->map(fn ($o) => [
                'tipo'      => $o->type,
                'estado'    => $o->status,
                'ciudad'    => $o->city,
                'creada_el' => $o->created_at?->toIso8601String(),
            ])->all(),
            'siguiendo' => $user->follows()->get()->map(fn ($f) => [
                'tipo' => $f->followable_type,
                'id'   => $f->followable_id,
            ])->all(),
        ];
    }
}
