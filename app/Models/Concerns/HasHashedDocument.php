<?php

namespace App\Models\Concerns;

use App\Support\Privacy\DocumentHasher;

/**
 * Centro de Privacidad · Cifra `document` y mantiene `document_hash` al día.
 *
 * El modelo debe tener la columna `document` (cifrada vía AsEncryptedString) y
 * `document_hash`. En cada guardado recalcula el blind index a partir del valor
 * en claro, de modo que las búsquedas por documento usen `document_hash`.
 */
trait HasHashedDocument
{
    public static function bootHasHashedDocument(): void
    {
        static::saving(function ($model) {
            $model->document_hash = DocumentHasher::hash($model->document);
        });
    }

    /** Scope: buscar por documento usando el blind index. */
    public function scopeWhereDocument($query, ?string $document)
    {
        return $query->where('document_hash', DocumentHasher::hash($document));
    }
}
