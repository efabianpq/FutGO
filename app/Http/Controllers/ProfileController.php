<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('profile.show');
    }

    /**
     * Sube o actualiza la foto de perfil del usuario.
     * Valida tipo (jpg/png/webp) y tamaño (máx 2 MB). Guarda en el disco público
     * y expone la URL. Borra la foto anterior si existía.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.required' => 'Seleccioná una imagen.',
            'avatar.image'    => 'El archivo debe ser una imagen.',
            'avatar.mimes'    => 'Formatos permitidos: JPG, PNG o WEBP.',
            'avatar.max'      => 'La imagen no puede superar los 2 MB.',
        ]);

        $user = $request->user();

        // Borrar la foto anterior (si vivía en nuestro disco público).
        $this->deleteStoredAvatar($user->avatar_url);

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_url = Storage::disk('public')->url($path);
        $user->save();

        return back()->with('status', 'Foto de perfil actualizada.');
    }

    /** Borra del disco público un archivo cuya URL pertenezca a /storage. */
    private function deleteStoredAvatar(?string $url): void
    {
        if (! $url) {
            return;
        }
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        if (str_starts_with($path, 'storage/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }
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
