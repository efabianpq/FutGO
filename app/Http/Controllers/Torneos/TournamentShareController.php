<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Services\Torneos\TournamentReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tarjetas COMPARTIBLES del torneo (Sesión E).
 *
 * Técnica: SVG renderizado desde plantillas Blade → PHP puro, SIN GD ni imagick
 * (portable a Hostinger). Cada tarjeta usa el branding de FUTGO y, si el torneo
 * tiene logo/banner, los incorpora. Se sirven como image/svg+xml (inline o, con
 * ?descargar=1, como adjunto para guardar/compartir).
 */
class TournamentShareController extends Controller
{
    public function __construct(private TournamentReportService $report) {}

    /** Tarjetas agregadas: goleadores | posiciones | mvp. */
    public function card(Request $request, Tournament $tournament, string $card): Response
    {
        abort_unless($tournament->isPublic(), 404);

        $view = match ($card) {
            'goleadores' => ['torneos.public.share.goleadores', [
                'tournament' => $tournament,
                'scorers'    => $this->report->topScorers($tournament, 8),
            ]],
            'posiciones' => ['torneos.public.share.posiciones', [
                'tournament' => $tournament,
                'phases'     => $this->report->groupStandings($tournament),
            ]],
            'mvp' => ['torneos.public.share.mvp', [
                'tournament' => $tournament,
                'match'      => $this->report->latestMvpMatch($tournament),
            ]],
            default => abort(404),
        };

        return $this->svg(view($view[0], $view[1])->render(), $request, "futgo-{$tournament->slug}-{$card}");
    }

    /** Tarjeta del resultado de un partido finalizado. */
    public function matchCard(Request $request, Tournament $tournament, TournamentMatch $match): Response
    {
        abort_unless($tournament->isPublic(), 404);
        abort_unless($match->phase && $match->phase->tournament_id === $tournament->id, 404);
        abort_unless($match->isFinished(), 404);

        $match->load(['homeTeam:id,name,color', 'awayTeam:id,name,color', 'phase:id,name']);

        $svg = view('torneos.public.share.partido', compact('tournament', 'match'))->render();

        return $this->svg($svg, $request, "futgo-{$tournament->slug}-partido-{$match->match_number}");
    }

    /** Respuesta SVG (inline por defecto; adjunto si ?descargar=1). */
    private function svg(string $svg, Request $request, string $filename): Response
    {
        $disposition = $request->boolean('descargar')
            ? "attachment; filename=\"{$filename}.svg\""
            : 'inline';

        return response($svg, 200, [
            'Content-Type'        => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => $disposition,
            'Cache-Control'       => 'public, max-age=300',
        ]);
    }
}
