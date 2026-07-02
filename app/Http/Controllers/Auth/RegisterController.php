<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RegistrationConfirmationNotification;
use App\Services\Torneos\ProfileClaimService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, ProfileClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:50'],
            'apellido' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'telefono' => ['required', 'string', 'regex:/^[0-9]{7,15}$/'],
            // Documento opcional: si se ingresa, habilita la detección de perfiles
            // 'por_verificar' reclamables (Limitación #2).
            'documento' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'telefono.regex' => 'El teléfono debe contener entre 7 y 15 dígitos numéricos (sin espacios ni símbolos).',
        ]);

        $user = User::create([
            'name' => trim($data['nombre'] . ' ' . $data['apellido']),
            'email' => strtolower($data['email']),
            'phone_whatsapp' => $data['telefono'],
            'document' => $data['documento'] ?? null,
            'password' => $data['password'],
            'role' => 'user',
            'notifications_enabled' => true,
            'email_verified_at' => now(),
        ]);

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
