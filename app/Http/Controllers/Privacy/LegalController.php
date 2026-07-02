<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\LegalDocument;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Renderizado público de los documentos legales.
 *
 * Sirve la versión vigente (is_current) desde la BD — no vistas fijas — para que
 * el versionado sea la única fuente de verdad. Sin auth: son documentos públicos
 * exigidos por Play Store / App Store y por la Ley 1581/2012.
 */
class LegalController extends Controller
{
    public function show(string $type): View
    {
        abort_unless(array_key_exists($type, LegalDocument::TYPES), 404);

        $document = LegalDocument::currentFor($type);

        abort_if($document === null, 404);

        return view('legal.documento', [
            'document' => $document,
            'allTypes' => LegalDocument::TYPES,
        ]);
    }
}
