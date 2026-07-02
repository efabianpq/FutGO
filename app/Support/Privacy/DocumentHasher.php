<?php

namespace App\Support\Privacy;

use Illuminate\Support\Facades\Config;

/**
 * Centro de Privacidad · Blind index para el número de documento.
 *
 * `document` se cifra en BD con AsEncryptedString, por lo que no admite
 * `WHERE document = ?`. Este helper produce un hash determinista (HMAC-SHA256
 * con APP_KEY) del documento NORMALIZADO, que sí es indexable y comparable.
 *
 * La normalización debe ser idéntica a la del reclamo de perfil: dos personas
 * pueden escribir "12.345.678" y "12345678" y son el mismo documento.
 */
class DocumentHasher
{
    /** Minúsculas y sin separadores (puntos, guiones, espacios). */
    public static function normalize(?string $doc): ?string
    {
        if ($doc === null) {
            return null;
        }

        $clean = preg_replace('/[^a-z0-9]/i', '', $doc);

        return $clean === '' ? null : mb_strtolower($clean);
    }

    /** HMAC del documento normalizado (o null si no hay documento). */
    public static function hash(?string $doc): ?string
    {
        $normalized = static::normalize($doc);

        if ($normalized === null) {
            return null;
        }

        return hash_hmac('sha256', $normalized, static::key());
    }

    private static function key(): string
    {
        $key = Config::get('app.key');

        // Soporta el formato "base64:..." de Laravel.
        if (is_string($key) && str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return (string) $key;
    }
}
