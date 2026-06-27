<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Venue;
use App\Models\Torneos\TournamentMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FutGO Social — Sesión S3-B · Venues (canchas).
 *
 * Listado/perfil público sin auth; CRUD autenticado (cualquier usuario puede
 * registrar, solo el registrador o admin puede editar). Endpoint JSON de
 * búsqueda para autocompletado en formularios de amistosos/oportunidades.
 */
class VenueController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // Exploración pública
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $city   = $request->input('ciudad');
        $search = $request->input('q');

        $query = Venue::active()->orderBy('name');

        if ($city) {
            $query->inCity($city);
        }

        if ($search) {
            $query->search($search);
        }

        $venues = $query->paginate(20)->withQueryString();

        $cities = Venue::active()->distinct()->orderBy('city')->pluck('city');

        return view('social.canchas.index', compact('venues', 'cities', 'city', 'search'));
    }

    /** Perfil público de la cancha: datos, partidos, disponibilidad. */
    public function show(Venue $venue): View
    {
        abort_if(! $venue->is_active, 404);

        $played     = $venue->playedFriendlyMatches()->limit(10)->get();
        $upcoming   = $venue->upcomingMatches()->limit(5)->get();
        $isOccupied = $upcoming->isNotEmpty();

        return view('social.canchas.show', compact('venue', 'played', 'upcoming', 'isOccupied'));
    }

    /** JSON: búsqueda por ciudad (para autocompletado Alpine). */
    public function search(Request $request): JsonResponse
    {
        $city   = $request->input('ciudad', '');
        $q      = $request->input('q', '');

        $venues = Venue::active()
            ->when($city, fn ($qr) => $qr->inCity($city))
            ->when($q, fn ($qr) => $qr->search($q))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'city', 'address', 'surface_type']);

        return response()->json($venues->map(fn ($v) => [
            'id'           => $v->id,
            'name'         => $v->name,
            'slug'         => $v->slug,
            'city'         => $v->city,
            'address'      => $v->address,
            'surface'      => $v->surfaceLabel(),
            'url'          => route('social.canchas.show', $v->slug),
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD autenticado
    // ─────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        $surfaces = Venue::SURFACES;
        return view('social.canchas.create', compact('surfaces'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'min:3', 'max:120'],
            'city'             => ['required', 'string', 'max:80'],
            'address'          => ['nullable', 'string', 'max:200'],
            'surface_type'     => ['nullable', 'string', 'in:' . implode(',', array_keys(Venue::SURFACES))],
            'approx_capacity'  => ['nullable', 'integer', 'min:1', 'max:100000'],
            'maps_url'         => ['nullable', 'url', 'max:1000'],
        ]);

        $venue = Venue::create([
            ...$data,
            'registered_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('social.canchas.show', $venue->slug)
            ->with('success', "Cancha \"{$venue->name}\" registrada.");
    }

    public function edit(Venue $venue): View
    {
        $user = auth()->user();
        abort_unless($venue->canBeEditedBy($user), 403);

        $surfaces = Venue::SURFACES;
        return view('social.canchas.edit', compact('venue', 'surfaces'));
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($venue->canBeEditedBy($user), 403);

        $data = $request->validate([
            'name'             => ['required', 'string', 'min:3', 'max:120'],
            'city'             => ['required', 'string', 'max:80'],
            'address'          => ['nullable', 'string', 'max:200'],
            'surface_type'     => ['nullable', 'string', 'in:' . implode(',', array_keys(Venue::SURFACES))],
            'approx_capacity'  => ['nullable', 'integer', 'min:1', 'max:100000'],
            'maps_url'         => ['nullable', 'url', 'max:1000'],
            'is_active'        => ['boolean'],
        ]);

        $venue->update($data);

        return redirect()->route('social.canchas.show', $venue->slug)
            ->with('success', 'Datos de la cancha actualizados.');
    }
}
