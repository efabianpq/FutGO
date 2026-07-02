<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        // Bloqueo progresivo por cuenta+IP — capa adicional sobre el throttle
        // global de la ruta ('auth', 5/min por IP en AppServiceProvider): esto
        // cubre credential stuffing distribuido (misma cuenta, muchas IPs).
        $lockKey = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($lockKey, 5)) {
            $seconds = RateLimiter::availableIn($lockKey);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intentá de nuevo en {$seconds} segundos.",
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($lockKey, 300);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales son incorrectas.',
            ]);
        }

        RateLimiter::clear($lockKey);

        $request->session()->regenerate();

        $user = $request->user();

        // H1: el admin de plataforma va a su dashboard; el resto va a Mi Carrera.
        if ($user->isAdmin()) {
            return redirect()->intended(route('dashboard'));
        }

        return redirect()->intended(route('torneos.mi-carrera'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
