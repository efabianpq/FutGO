<?php

namespace App\Http\Controllers;

use App\Models\Social\Opportunity;
use App\Services\Social\FeedService;
use App\Services\Social\SportsAgendaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla de Inicio (v3) — dashboard de entrada del usuario.
 *
 * No es un dominio nuevo: agrega en una sola vista lo que ya existe —
 * agenda (qué tengo pendiente), recordatorios (qué requiere mi acción ya),
 * sugerencias (oportunidades de mi ciudad) y novedades (preview del Feed).
 */
class DashboardController extends Controller
{
    public function __construct(
        private SportsAgendaService $agenda,
        private FeedService $feed,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $now    = now();
        $agenda = $this->agenda->for($user, $now);

        // Recordatorios: lo que requiere acción del usuario YA (resultado a cargar,
        // convocatoria a responder). Se muestran arriba, destacados.
        $reminders = $agenda
            ->filter(fn ($i) => in_array($i->status, ['resultado_pendiente', 'convocatoria_pendiente'], true))
            ->take(5)
            ->values();

        // Próximo: ítems con fecha de hoy en adelante, agrupados por día.
        $upcoming = $agenda
            ->filter(fn ($i) => $i->date && $i->date->greaterThanOrEqualTo($now->copy()->startOfDay()))
            ->take(8)
            ->values();
        $grouped = $upcoming->groupBy(fn ($i) => $i->date->format('Y-m-d'));

        // Sugeridas: oportunidades activas de la ciudad del usuario (excluye propias).
        $suggested = collect();
        if ($user->city) {
            $suggested = Opportunity::query()
                ->visible()
                ->active()
                ->inCity($user->city)
                ->where('user_id', '!=', $user->id)
                ->latest()
                ->limit(4)
                ->get();
        }

        // Novedades: preview del Feed (sin marcar leído — el badge baja recién en /feed).
        $feedPreview = $this->feed->relevantQuery($user)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('inicio', [
            'user'        => $user,
            'grouped'     => $grouped,
            'reminders'   => $reminders,
            'suggested'   => $suggested,
            'feedPreview' => $feedPreview,
            'agendaTotal' => $agenda->count(),
        ]);
    }
}
