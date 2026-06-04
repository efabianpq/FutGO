<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use Illuminate\View\View;

/**
 * "Mis Equipos" (unifica el antiguo Panel Capitán).
 *
 * Equipos PERMANENTES (transversales a torneos): los que el usuario capitanea
 * (gestionables) y aquellos donde es jugador. Desde acá también se crean equipos
 * nuevos — al crear, el usuario queda como capitán.
 */
class MyTeamsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $captainClubs = Club::where('captain_user_id', $user->id)
            ->withCount(['players as players_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount('teams as tournaments_count')
            ->orderBy('name')
            ->get();

        // Equipos donde es jugador pero NO capitán.
        $memberClubIds = ClubPlayer::where('user_id', $user->id)
            ->where('is_captain', false)
            ->pluck('club_id');

        $memberClubs = Club::whereIn('id', $memberClubIds)
            ->where('captain_user_id', '!=', $user->id)
            ->with('captain')
            ->withCount(['players as players_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();

        return view('torneos.mis-equipos', compact('captainClubs', 'memberClubs'));
    }
}
