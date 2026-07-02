<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Panel principal (hub).
 *
 * Solo lectura: resume qué datos tenemos del usuario, qué versiones de políticas
 * aceptó y ofrece los accesos directos a cada sección.
 */
class CenterController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('privacy.center.index', [
            'user'       => $user,
            'settings'   => $user->privacy(),
            'privacyDoc' => LegalDocument::currentFor('privacy'),
            'termsDoc'   => LegalDocument::currentFor('terms'),
        ]);
    }
}
