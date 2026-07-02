<?php

namespace App\Http\Controllers;

use App\Services\Torneos\ProfileClaimService;
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

        $this->deleteFromMediaDisk($user->avatar_url);

        $disk = config('filesystems.media_disk', 'public');
        $path = $request->file('avatar')->store('avatars', $disk);
        $user->avatar_url = Storage::disk($disk)->url($path);
        $user->save();

        return back()->with('status', 'Foto de perfil actualizada.');
    }

    /** Borra del disco de medios configurado un archivo a partir de su URL pública. */
    private function deleteFromMediaDisk(?string $url): void
    {
        if (! $url) {
            return;
        }
        $disk = config('filesystems.media_disk', 'public');
        $base = rtrim(Storage::disk($disk)->url(''), '/');
        if ($base && str_starts_with($url, $base . '/')) {
            Storage::disk($disk)->delete(substr($url, strlen($base) + 1));
        }
    }

    public function update(Request $request, ProfileClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'phone_whatsapp' => ['required', 'string', 'regex:/^[0-9]{7,15}$/'],
            'document' => ['nullable', 'string', 'max:40'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'accepts_direct_messages' => ['sometimes', 'boolean'],
            // FutGO Social: ciudad y nivel alimentan el matching y el Feed.
            'city' => ['nullable', 'string', 'max:120'],
            'play_level' => ['nullable', \Illuminate\Validation\Rule::in(\App\Models\User::PLAY_LEVELS)],
        ], [
            'phone_whatsapp.regex' => 'El teléfono debe contener entre 7 y 15 dígitos numéricos.',
            'document.max' => 'El documento no puede superar los 40 caracteres.',
        ]);

        // Asignación explícita (auditoría mass-assignment P-1): nunca $request->all().
        $user = $request->user();
        $documentChanged = ($data['document'] ?? null) !== $user->document;
        $user->phone_whatsapp = $data['phone_whatsapp'];
        $user->document = $data['document'] ?? null;
        $user->notifications_enabled = $request->boolean('notifications_enabled');
        $user->accepts_direct_messages = $request->boolean('accepts_direct_messages');
        $user->city = $data['city'] ?? null;
        $user->play_level = $data['play_level'] ?? null;
        $user->save();

        \App\Services\Privacy\AuditLogger::record('profile_updated', $user, null, [
            'document_changed' => $documentChanged,
        ]);

        // Al cargar/cambiar el documento, detectar perfiles 'por_verificar'
        // reclamables (Limitación #2).
        if ($documentChanged && ! empty($user->document)) {
            $candidates = $claims->countCandidatesFor($user);
            if ($candidates > 0) {
                return back()
                    ->with('status', 'Perfil actualizado.')
                    ->with('claim_candidates', $candidates);
            }
        }

        return back()->with('status', 'Perfil actualizado.');
    }
}
