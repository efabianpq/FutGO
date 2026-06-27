<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Services\Social\SportsAgendaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FutGO Social — Fase 2 · Sesión S2-A · Agenda deportiva unificada.
 *
 * Vista de lectura: agrega todo lo que el usuario tiene pendiente o programado
 * (partidos de torneo, amistosos, convocatorias, oportunidades por vencer),
 * agrupado por día y en orden cronológico.
 */
class AgendaController extends Controller
{
    public function __construct(private SportsAgendaService $agenda) {}

    public function index(Request $request): View
    {
        $items = $this->agenda->for($request->user());

        // Agrupa por día (Y-m-d) preservando el orden cronológico; los ítems sin
        // fecha caen en una clave 'sin-fecha' que la vista muestra al final.
        $grouped = $items->groupBy(fn ($item) => $item->date?->format('Y-m-d') ?? 'sin-fecha');

        return view('social.agenda.index', [
            'grouped' => $grouped,
            'total'   => $items->count(),
        ]);
    }
}
