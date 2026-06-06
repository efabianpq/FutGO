<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\TournamentReportService;
use Illuminate\View\View;

/**
 * Portal PÚBLICO del torneo (/t/{slug}) — Sesión E.
 *
 * Sin autenticación. Solo torneos con visibility='public'. Pensado para abrirse
 * desde un link de WhatsApp en el celular: liviano, responsive y con datos ya
 * cargados sin N+1 (TournamentReportService).
 *
 * NUNCA expone datos sensibles de usuarios (email/teléfono/documento): el servicio
 * carga del usuario solo id y name.
 */
class PublicTournamentController extends Controller
{
    public function __construct(private TournamentReportService $report) {}

    /**
     * H9: portal de exploración de torneos públicos (menú "Torneos").
     *
     * Lista los torneos con visibility='public' separados en:
     *  - "Inscripciones abiertas" (status=open) → un capitán puede inscribir su equipo.
     *  - "En juego" (status=in_progress) → seguimiento del torneo en curso.
     *
     * Accesible sin autenticación. La inscripción sí requiere estar logueado.
     */
    public function index(): View
    {
        $base = Tournament::query()
            ->where('visibility', 'public')
            ->withCount(['teams as approved_teams_count' => fn ($q) => $q->where('status', 'approved')]);

        $open = (clone $base)->where('status', 'open')
            ->orderByRaw('starts_at IS NULL')
            ->orderBy('starts_at')
            ->get();

        $inProgress = (clone $base)->where('status', 'in_progress')
            ->orderByDesc('starts_at')
            ->get();

        // Para usuarios autenticados con módulo torneos: clubs que capitanean
        // (para ofrecer "Inscribir mi equipo" directo).
        $isCaptain = false;
        if (($user = auth()->user()) && $user->hasTorneosAccess()) {
            $isCaptain = $user->isCaptainAnywhere();
        }

        return view('torneos.public.index', compact('open', 'inProgress', 'isCaptain'));
    }

    public function show(Tournament $tournament): View
    {
        // Los torneos privados no existen para el público (404, no 403: no revela su existencia).
        abort_unless($tournament->isPublic(), 404);

        $standings = $this->report->groupStandings($tournament);
        $results   = $this->report->finishedMatches($tournament, 12);
        $upcoming  = $this->report->upcomingMatches($tournament, 12);
        $scorers   = $this->report->topScorers($tournament, 10);
        $summary   = $this->report->summary($tournament);
        $sponsors  = $tournament->sponsors()->active()->orderBy('sort_order')->orderBy('id')->get();

        return view('torneos.public.show', compact(
            'tournament', 'standings', 'results', 'upcoming', 'scorers', 'summary', 'sponsors'
        ));
    }
}
