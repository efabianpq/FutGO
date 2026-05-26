<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => trim($data['nombre'] . ' ' . $data['apellido']),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => 'user',
            'is_active' => false,
            'notifications_enabled' => true,
            'email_verified_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('activate.show');
    }
}
