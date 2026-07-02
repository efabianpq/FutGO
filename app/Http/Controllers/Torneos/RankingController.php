<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\FutgoRanking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ranking FUTGO (Sesión F). Lee la tabla CACHEADA futgo_rankings (no calcula nada
 * en el request). Filtra por tipo (jugadores/equipos) y alcance (global/ciudad/categoría).
 */
class RankingController extends Controller
{
    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), ['player', 'team'], true)
            ? $request->query('type') : 'player';

        $scopeType = in_array($request->query('scope'), ['global', 'city', 'category'], true)
            ? $request->query('scope') : 'global';

        $scopeValue = $scopeType === 'global' ? null : $request->query('value');

        $rankings = FutgoRanking::where('subject_type', $type)
            ->forScope($scopeType, $scopeValue)
            ->orderBy('position')
            ->limit(100)
            ->get();

        $cities = DB::table('tournaments')->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $categories = DB::table('tournaments')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        // Deuda #10: el ranking es cache — mostrar cuándo se calculó por última vez.
        $lastCalculated = FutgoRanking::max('calculated_at');

        return view('torneos.ranking.index', compact(
            'rankings', 'type', 'scopeType', 'scopeValue', 'cities', 'categories', 'lastCalculated'
        ));
    }
}
