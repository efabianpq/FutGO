<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Privacy\ParentalConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Consentimiento parental de menores.
 */
class GuardianConsentController extends Controller
{
    public function __construct(private ParentalConsentService $parental)
    {
    }

    /** Confirmación del representante (ruta pública firmada). */
    public function confirm(Request $request, User $user): View
    {
        // La firma ya fue validada por el middleware 'signed'.
        $this->parental->confirm($user);

        return view('privacy.parental.confirmed', ['minor' => $user]);
    }

    /** Página informativa para el menor con la cuenta pendiente. */
    public function pending(Request $request): View|RedirectResponse
    {
        if (! $request->user()->pending_guardian_consent) {
            return redirect()->route('inicio');
        }

        return view('privacy.parental.pending', ['user' => $request->user()]);
    }

    /** Reenvía el correo al representante. */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->pending_guardian_consent) {
            $this->parental->sendRequest($user);
        }

        return back()->with('status', 'Le reenviamos el correo a tu representante.');
    }
}
