<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RegistrationConfirmationNotification;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:50'],
            'apellido' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'telefono' => ['required', 'string', 'regex:/^[0-9]{7,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'telefono.regex' => 'El teléfono debe contener entre 7 y 15 dígitos numéricos (sin espacios ni símbolos).',
        ]);

        $user = User::create([
            'name' => trim($data['nombre'] . ' ' . $data['apellido']),
            'email' => strtolower($data['email']),
            'phone_whatsapp' => $data['telefono'],
            'password' => $data['password'],
            'role' => 'user',
            'is_active' => false,
            'notifications_enabled' => true,
            'email_verified_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        try {
            $user->notify(new RegistrationConfirmationNotification($user));
        } catch (\Throwable $e) {
            Log::warning('Registration email failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return redirect()->route('activate.show');
    }
}
