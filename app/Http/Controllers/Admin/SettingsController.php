<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'prize_pool' => Settings::prizePool(),
            'tournament_name' => Settings::tournamentName(),
            'welcome_message' => Settings::welcomeMessage(),
            'video_url' => Settings::videoUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'prize_pool' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'tournament_name' => ['required', 'string', 'max:100'],
            'welcome_message' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:255', 'url'],
        ]);

        if ($data['prize_pool'] === null || $data['prize_pool'] === '') {
            Settings::clearPrizePool();
        } else {
            Settings::setPrizePool((int) $data['prize_pool']);
        }

        Settings::set(Settings::TOURNAMENT_NAME, $data['tournament_name']);
        Settings::set(Settings::WELCOME_MESSAGE, $data['welcome_message'] ?? '');
        Settings::set(Settings::VIDEO_URL, $data['video_url'] ?? '');

        return back()->with('status', 'Configuración guardada.');
    }
}
