<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentSponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestión básica de PATROCINADORES del torneo (Sesión G).
 * Solo el espacio: nombre, logo y enlace. SIN lógica de cobro ni facturación.
 */
class SponsorController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $this->authorizeManage($tournament);

        $sponsors = $tournament->sponsors()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.torneos.sponsors.index', compact('tournament', 'sponsors'));
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeManage($tournament);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'logo_url'   => ['nullable', 'url', 'max:255'],
            'link_url'   => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'logo_url.url' => 'El logo debe ser una URL válida (https://...).',
            'link_url.url' => 'El enlace debe ser una URL válida (https://...).',
        ]);

        $tournament->sponsors()->create([
            'name'       => $data['name'],
            'logo_url'   => $data['logo_url'] ?? null,
            'link_url'   => $data['link_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return back()->with('status', 'Patrocinador agregado.');
    }

    public function destroy(Tournament $tournament, TournamentSponsor $sponsor): RedirectResponse
    {
        $this->authorizeManage($tournament);
        abort_unless($sponsor->tournament_id === $tournament->id, 404);

        $sponsor->delete();

        return back()->with('status', 'Patrocinador eliminado.');
    }

    private function authorizeManage(Tournament $tournament): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $tournament->tournamentAdmins()->where('user_id', $user->id)->exists(),
            403
        );
    }
}
