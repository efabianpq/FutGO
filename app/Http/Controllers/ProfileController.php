<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('profile.show');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone_whatsapp' => ['required', 'string', 'regex:/^[0-9]{7,15}$/'],
            'notifications_enabled' => ['sometimes', 'boolean'],
        ], [
            'phone_whatsapp.regex' => 'El teléfono debe contener entre 7 y 15 dígitos numéricos.',
        ]);

        $user = $request->user();
        $user->phone_whatsapp = $data['phone_whatsapp'];
        $user->notifications_enabled = $request->boolean('notifications_enabled');
        $user->save();

        return back()->with('status', 'Perfil actualizado.');
    }
}
