<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\WelcomeNotification;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function show(): View
    {
        return view('auth.activate');
    }

    public function store(Request $request, RankingService $ranking): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $code = strtoupper(trim($data['code']));
        $user = $request->user();

        $activated = DB::transaction(function () use ($code, $user) {
            $invitation = DB::table('invitation_codes')
                ->where('code', $code)
                ->where('is_active', true)
                ->where('is_used', false)
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                return false;
            }

            DB::table('invitation_codes')->where('id', $invitation->id)->update([
                'is_used' => true,
                'used_by_user_id' => $user->id,
                'used_at' => now(),
                'updated_at' => now(),
            ]);

            $user->forceFill([
                'is_active' => true,
                'invitation_code' => $code,
            ])->save();

            return true;
        });

        if ($activated) {
            $ranking->ensureRankingRow($user->id);
        }

        if (! $activated) {
            return back()->withErrors([
                'code' => 'Código incorrecto o ya utilizado.',
            ]);
        }

        // El notify va fuera del transaction: si el email falla, la activación
        // ya quedó confirmada en BD y no se revierte.
        $user->notify(new WelcomeNotification());

        return redirect()->route('dashboard')->with('status', '¡Bienvenido! Ya puedes comenzar a pronosticar.');
    }
}
