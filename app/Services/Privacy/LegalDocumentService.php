<?php

namespace App\Services\Privacy;

use App\Models\Privacy\LegalDocument;
use Illuminate\Support\Facades\DB;

/**
 * Centro de Privacidad · Publicación versionada de documentos legales.
 *
 * Publicar una versión nueva marca la anterior del mismo tipo como histórica
 * (is_current=false) — nunca se edita el contenido de una versión ya publicada.
 * Al cambiar la versión vigente de terms/privacy, EnsureConsentUpToDate obligará
 * a los usuarios a re-aceptar (comparando contra users.current_{...}_version).
 */
class LegalDocumentService
{
    public function publish(array $data, ?int $userId = null): LegalDocument
    {
        return DB::transaction(function () use ($data, $userId) {
            LegalDocument::ofType($data['type'])->update(['is_current' => false]);

            return LegalDocument::create([
                'type'               => $data['type'],
                'version'            => $data['version'],
                'title'              => $data['title'],
                'content'            => $data['content'],
                'summary_of_changes' => $data['summary_of_changes'] ?? null,
                'published_at'       => now(),
                'is_current'         => true,
                'created_by_user_id' => $userId,
            ]);
        });
    }
}
