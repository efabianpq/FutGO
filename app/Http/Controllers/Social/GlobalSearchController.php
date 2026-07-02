<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\Venue;
use App\Models\Torneos\Club;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buscador global del header (🔍). Cruza las cuatro entidades descubribles del
 * ecosistema — jugadores, clubes, torneos públicos y canchas — en una sola
 * consulta de texto. Es una vista de lectura: no expone datos de contacto.
 */
class GlobalSearchController extends Controller
{
    /** Máximo de resultados por categoría (lista, no buscador pesado). */
    private const PER_GROUP = 8;

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $players = $clubs = $tournaments = $venues = collect();

        if (mb_strlen($term) >= 2) {
            $like = '%' . $term . '%';

            // Jugadores: solo identidades públicas (con futgo_id). Nunca email/tel.
            // Respeta privacy_settings: excluye a quien desactivó searchable o
            // public_profile (si no tiene fila, cuenta como visible — defaults).
            $players = User::query()
                ->whereNotNull('futgo_id')
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('futgo_id', 'like', $like))
                ->whereDoesntHave('privacySetting', fn ($q) => $q->where('searchable', false)->orWhere('public_profile', false))
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get(['id', 'name', 'futgo_id', 'avatar_url', 'city', 'play_level']);

            // Clubes por nombre.
            $clubs = Club::query()
                ->where('name', 'like', $like)
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get(['id', 'name', 'slug', 'shield_url', 'play_level']);

            // Torneos PÚBLICOS por nombre.
            $tournaments = Tournament::query()
                ->where('visibility', 'public')
                ->where('name', 'like', $like)
                ->orderByDesc('id')
                ->limit(self::PER_GROUP)
                ->get(['id', 'name', 'slug', 'status', 'format']);

            // Canchas por nombre o ciudad.
            $venues = Venue::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('city', 'like', $like))
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get(['id', 'name', 'slug', 'city', 'surface_type']);
        }

        $total = $players->count() + $clubs->count() + $tournaments->count() + $venues->count();

        return view('social.search.index', compact('term', 'players', 'clubs', 'tournaments', 'venues', 'total'));
    }
}
