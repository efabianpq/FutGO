<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\PrivacySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Configuración de privacidad del perfil.
 *
 * El usuario decide qué se muestra públicamente (§6 del informe). Diferencial
 * frente a otras plataformas.
 */
class SettingsController extends Controller
{
    private const TOGGLES = [
        'show_email', 'show_phone', 'show_birthdate', 'show_city', 'show_photo',
        'show_stats', 'show_teams', 'show_history', 'public_profile', 'searchable',
        'indexable_by_search_engines',
    ];

    public function edit(Request $request): View
    {
        return view('privacy.center.settings', [
            'settings' => $request->user()->privacy(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'allow_messages' => ['required', Rule::in(PrivacySetting::ALLOW_MESSAGES)],
        ]);

        $data = ['allow_messages' => $validated['allow_messages']];

        // Los toggles ausentes en el POST significan "off".
        foreach (self::TOGGLES as $toggle) {
            $data[$toggle] = $request->boolean($toggle);
        }

        $request->user()->privacy()->update($data);

        \App\Services\Privacy\AuditLogger::record('privacy_settings_updated', $request->user());

        return back()->with('status', 'Configuración de privacidad actualizada.');
    }
}
