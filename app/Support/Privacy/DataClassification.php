<?php

namespace App\Support\Privacy;

/**
 * Centro de Privacidad · Clasificación canónica de datos personales.
 *
 * Fuente de verdad para saber qué campos son públicos (sujetos a
 * privacy_settings), privados (solo dueño/admin) o muy sensibles (cifrados,
 * nunca en respuestas públicas ni logs). Usada por los API Resources, el
 * buscador y la documentación (docs/CLASIFICACION_DATOS.md).
 */
class DataClassification
{
    /** Visibles públicamente según privacy_settings del usuario. */
    public const PUBLIC = [
        'name', 'futgo_id', 'avatar_url', 'city', 'play_level',
        'stats', 'clubs', 'achievements', 'ranking',
    ];

    /** Solo el propio usuario o un admin. */
    public const PRIVATE = [
        'email', 'phone_whatsapp', 'birthdate', 'ip', 'sessions', 'remember_token',
    ];

    /** Muy sensibles: cifrados en BD, jamás en respuestas públicas ni logs. */
    public const SENSITIVE = [
        'document', 'document_hash', 'credential_validations',
    ];

    /** Nunca deben aparecer en logs ni auditoría. */
    public const NEVER_LOG = [
        'password', 'remember_token', 'document', 'token', 'secret',
    ];
}
