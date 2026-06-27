<?php

namespace App\Support\Social;

use Illuminate\Support\Str;

/**
 * FutGO Social — filtro básico de contenido (moderación día-uno).
 *
 * Las oportunidades y los mensajes de respuesta son texto libre desde la Fase 1
 * (ver PROPUESTA_FUTGO_SOCIAL_v3 §9). No pretende ser un sistema de moderación
 * sofisticado: longitud máxima (la valida el Form Request/regla) + una lista
 * mínima de palabras prohibidas. El botón "reportar" (content_reports) cubre el
 * resto con revisión manual del admin.
 */
class ContentFilter
{
    /**
     * Lista mínima de términos vetados (insultos/spam evidente). Se compara
     * normalizada (sin acentos, en minúscula) contra los "tokens" del texto, de
     * modo que detecta la palabra aunque venga con mayúsculas o signos pegados.
     */
    public const BANNED_WORDS = [
        'puto', 'puta', 'mierda', 'idiota', 'imbecil', 'estupido',
        'maricon', 'pendejo', 'concha', 'forro', 'gil',
        // Spam evidente
        'viagra', 'casino', 'xxx', 'porno',
    ];

    /** ¿El texto contiene alguna palabra prohibida? */
    public static function hasBannedWord(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        $normalized = static::normalize($text);

        // Tokeniza por cualquier separador que no sea letra/número.
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokenSet = array_flip($tokens);

        foreach (static::BANNED_WORDS as $word) {
            if (isset($tokenSet[$word])) {
                return true;
            }
        }

        return false;
    }

    /** Normaliza: minúsculas + sin acentos (para comparar de forma robusta). */
    private static function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }
}
