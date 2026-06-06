<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales son incorrectas.',
            ]);
        }

        $request->session()->regenerate();

        if (! $request->user()->is_active) {
            return redirect()->route('activate.show');
        }

        $user = $request->user();

        // H1: el admin de plataforma va a su dashboard.
        // Usuarios con acceso a torneos van a Mi Carrera (torneos.mi-carrera).
        // Usuarios solo-polla van al dashboard (que redirige a predictions.index).
        if ($user->isAdmin()) {
            return redirect()->intended(route('dashboard'));
        }

        if ($user->hasTorneosAccess()) {
            return redirect()->intended(route('torneos.mi-carrera'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
