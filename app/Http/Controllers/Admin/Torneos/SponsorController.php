<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentSponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'logo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'link_url'   => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'logo.image'   => 'El logo debe ser una imagen (jpg, png o webp).',
            'link_url.url' => 'El enlace debe ser una URL válida (https://...).',
        ]);

        $sponsor = $tournament->sponsors()->create([
            'name'       => $data['name'],
            'link_url'   => $data['link_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        if ($request->hasFile('logo')) {
            $this->storeLogo($sponsor, $request);
        }

        return back()->with('status', 'Patrocinador agregado.');
    }

    public function update(Request $request, Tournament $tournament, TournamentSponsor $sponsor): RedirectResponse
    {
        $this->authorizeManage($tournament);
        abort_unless($sponsor->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'logo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'link_url'   => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'logo.image'   => 'El logo debe ser una imagen (jpg, png o webp).',
            'link_url.url' => 'El enlace debe ser una URL válida (https://...).',
        ]);

        $sponsor->name       = $data['name'];
        $sponsor->link_url   = $data['link_url'] ?? null;
        $sponsor->sort_order = $data['sort_order'] ?? 0;
        $sponsor->save();

        if ($request->hasFile('logo')) {
            $this->storeLogo($sponsor, $request);
        }

        return back()->with('status', 'Patrocinador actualizado.');
    }

    public function toggleActive(Tournament $tournament, TournamentSponsor $sponsor): RedirectResponse
    {
        $this->authorizeManage($tournament);
        abort_unless($sponsor->tournament_id === $tournament->id, 404);

        $sponsor->update(['is_active' => ! $sponsor->is_active]);

        return back()->with('status', $sponsor->is_active ? 'Patrocinador activado.' : 'Patrocinador inactivado.');
    }

    public function destroy(Tournament $tournament, TournamentSponsor $sponsor): RedirectResponse
    {
        $this->authorizeManage($tournament);
        abort_unless($sponsor->tournament_id === $tournament->id, 404);

        $this->deleteLogoFile($sponsor);
        $sponsor->delete();

        return back()->with('status', 'Patrocinador eliminado.');
    }

    /** Guarda el logo subido en el disco configurado y borra el anterior. */
    private function storeLogo(TournamentSponsor $sponsor, Request $request): void
    {
        $diskName = config('filesystems.media_disk', 'public');
        $disk     = Storage::disk($diskName);

        $this->deleteLogoFile($sponsor);

        $path = $request->file('logo')->store("torneos/{$sponsor->tournament_id}/sponsors", $diskName);
        $sponsor->logo_url = $disk->url($path);
        $sponsor->save();
    }

    /** Borra el archivo de logo anterior del disco configurado, si existe. */
    private function deleteLogoFile(TournamentSponsor $sponsor): void
    {
        $previous = $sponsor->logo_url;
        if (! $previous) {
            return;
        }

        $diskName = config('filesystems.media_disk', 'public');
        $disk     = Storage::disk($diskName);
        $base     = rtrim($disk->url(''), '/');

        if ($base && str_starts_with($previous, $base . '/')) {
            $disk->delete(substr($previous, strlen($base) + 1));
        } elseif (str_starts_with($previous, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $previous));
        }
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
