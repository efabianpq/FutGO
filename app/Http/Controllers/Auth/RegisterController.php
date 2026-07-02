<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RegistrationConfirmationNotification;
use App\Rules\MinimumAge;
use App\Services\Privacy\ConsentService;
use App\Services\Torneos\ProfileClaimService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, ProfileClaimService $claims, ConsentService $consents): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:50'],
            'apellido' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'telefono' => ['required', 'string', 'regex:/^[0-9]{7,15}$/'],
            // Documento opcional: si se ingresa, habilita la detección de perfiles
            // 'por_verificar' reclamables (Limitación #2).
            'documento' => ['nullable', 'string', 'max:40'],
            // Fecha de nacimiento: edad mínima (Política para menores).
            'birthdate' => ['required', 'date', new MinimumAge()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Consentimiento explícito (Ley 1581/2012). Los dos primeros obligatorios.
            'accept_terms'   => ['accepted'],
            'accept_privacy' => ['accepted'],
            'accept_marketing' => ['nullable', 'boolean'],
        ], [
            'telefono.regex' => 'El teléfono debe contener entre 7 y 15 dígitos numéricos (sin espacios ni símbolos).',
            'accept_terms.accepted'   => 'Debes aceptar los Términos y Condiciones.',
            'accept_privacy.accepted' => 'Debes aceptar la Política de Privacidad.',
        ]);

        // Consentimiento parental para menores de 18 (si la feature está activa).
        $isMinor = Carbon::parse($data['birthdate'])->age < 18;
        $needsGuardian = $isMinor && config('privacy.parental_consent', true);

        if ($needsGuardian) {
            $request->validate([
                'guardian_email' => ['required', 'email', 'max:150', 'different:email'],
            ], [
                'guardian_email.required'  => 'Al ser menor de edad, indica el correo de tu padre, madre o representante legal.',
                'guardian_email.different' => 'El correo del representante debe ser distinto al tuyo.',
            ]);
        }

        $user = DB::transaction(function () use ($data, $request, $consents, $needsGuardian) {
            $user = User::create([
                'name' => trim($data['nombre'] . ' ' . $data['apellido']),
                'email' => strtolower($data['email']),
                'phone_whatsapp' => $data['telefono'],
                'document' => $data['documento'] ?? null,
                'birthdate' => $data['birthdate'],
                'guardian_email' => $needsGuardian ? strtolower($request->input('guardian_email')) : null,
                'pending_guardian_consent' => $needsGuardian,
                'password' => $data['password'],
                'role' => 'user',
                'notifications_enabled' => true,
                'email_verified_at' => now(),
            ]);

            $consents->recordRegistration($user, $request, (bool) ($data['accept_marketing'] ?? false));

            return $user;
        });

        \App\Services\Privacy\AuditLogger::record('consent_accepted', $user, null, ['source' => 'register']);

        // Menor con consentimiento parental pendiente: avisar al representante.
        if ($user->pending_guardian_consent) {
            app(\App\Services\Privacy\ParentalConsentService::class)->sendRequest($user);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Detección automática de perfiles reclamables por documento (Limitación #2).
        if (! empty($user->document)) {
            $candidates = $claims->countCandidatesFor($user);
            if ($candidates > 0) {
                $request->session()->flash('claim_candidates', $candidates);
            }
        }

        try {
            $user->notify(new RegistrationConfirmationNotification($user));
        } catch (\Throwable $e) {
            Log::warning('Registration email failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return redirect()->route('torneos.mi-carrera');
    }
}
