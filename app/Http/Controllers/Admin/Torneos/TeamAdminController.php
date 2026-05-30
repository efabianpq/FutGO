<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamAdminController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $this->authorizeAccess($tournament);

        $teams = $tournament->teams()
            ->withCount('players')
            ->with('captain')
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        return view('admin.torneos.equipos.index', compact('tournament', 'teams'));
    }

    public function show(Tournament $tournament, Team $team): View
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id, 404);

        $team->load(['captain', 'players.user']);

        return view('admin.torneos.equipos.show', compact('tournament', 'team'));
    }

    public function approve(Tournament $tournament, Team $team): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id, 404);

        $team->status = 'approved';
        $team->save();

        return back()->with('status', "Equipo \"{$team->name}\" aprobado.");
    }

    public function reject(Tournament $tournament, Team $team): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id, 404);

        $team->status = 'rejected';
        $team->save();

        return back()->with('status', "Equipo \"{$team->name}\" rechazado.");
    }

    private function authorizeAccess(Tournament $tournament): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $manages = $tournament->tournamentAdmins()
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($manages, 403);
    }
}
