<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Privacy\ConsentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Baja de comunicaciones comerciales.
 *
 * Enlace firmado incluido en el pie de todo email comercial ("¿Por qué recibís
 * esto? Darse de baja"). Nunca se envía marketing sin consentimiento
 * (user_consents type=marketing accepted=true).
 */
class MarketingUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user, ConsentService $consents): View
    {
        // La firma ya fue validada por el middleware 'signed'.
        $consents->updateMarketing($user, false, $request);

        return view('privacy.unsubscribed', ['user' => $user]);
    }
}
