<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateShareCardPng;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Services\Torneos\ShareCardPngService;
use App\Services\Torneos\TournamentReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tarjetas COMPARTIBLES del torneo.
 *
 * SVG: renderizado en Blade, sin dependencias de imagen (portable).
 * PNG: generado con GD (ShareCardPngService) y almacenado en caché.
 *       Si GD no está disponible, el endpoint degrada al SVG con Content-Type apropiado.
 */
class TournamentShareController extends Controller
{
    public function __construct(
        private TournamentReportService $report,
        private ShareCardPngService $png,
    ) {}

    // ─── SVG ─────────────────────────────────────────────────────────────────

    /** Tarjetas agregadas: goleadores | posiciones | mvp (SVG). */
    public function card(Request $request, Tournament $tournament, string $card): Response
    {
        abort_unless($tournament->isPublic(), 404);

        [$viewName, $data] = $this->cardViewAndData($tournament, $card);

        return $this->svg(view($viewName, $data)->render(), $request, "futgo-{$tournament->slug}-{$card}");
    }

    /** Tarjeta del resultado de un partido finalizado (SVG). */
    public function matchCard(Request $request, Tournament $tournament, TournamentMatch $match): Response
    {
        abort_unless($tournament->isPublic(), 404);
        abort_unless($match->phase && $match->phase->tournament_id === $tournament->id, 404);
        abort_unless($match->isFinished(), 404);

        $match->load(['homeTeam:id,name,color', 'awayTeam:id,name,color', 'phase:id,name']);
        $svg = view('torneos.public.share.partido', compact('tournament', 'match'))->render();

        return $this->svg($svg, $request, "futgo-{$tournament->slug}-partido-{$match->match_number}");
    }

    // ─── PNG ──────────────────────────────────────────────────────────────────

    /**
     * Tarjetas agregadas en PNG (goleadores | posiciones | mvp).
     *
     * Sirve desde caché si el PNG ya fue generado; en caso contrario lo genera
     * en el momento (sincrónico + almacena para la próxima). Si GD no está
     * disponible, sirve el SVG con Content-Type correcto.
     */
    public function cardPng(Request $request, Tournament $tournament, string $card): Response
    {
        abort_unless($tournament->isPublic(), 404);

        $storagePath = $this->png->storagePath($tournament->slug, $card);

        if (! $this->png->exists($storagePath)) {
            if (! $this->png->gdAvailable()) {
                // Degradar a SVG.
                [$viewName, $data] = $this->cardViewAndData($tournament, $card);
                return $this->svg(view($viewName, $data)->render(), $request, "futgo-{$tournament->slug}-{$card}");
            }

            [$viewName, $data] = $this->cardViewAndData($tournament, $card);
            $this->png->generateForTournament($tournament, $card, $data);
        }

        return $this->servePng($this->png->get($storagePath), "futgo-{$tournament->slug}-{$card}");
    }

    /**
     * Tarjeta de resultado de partido en PNG.
     */
    public function matchCardPng(Request $request, Tournament $tournament, TournamentMatch $match): Response
    {
        abort_unless($tournament->isPublic(), 404);
        abort_unless($match->phase && $match->phase->tournament_id === $tournament->id, 404);
        abort_unless($match->isFinished(), 404);

        $storagePath = $this->png->storagePath($tournament->slug, 'partido', $match->id);

        if (! $this->png->exists($storagePath)) {
            if (! $this->png->gdAvailable()) {
                $match->load(['homeTeam:id,name,color', 'awayTeam:id,name,color', 'phase:id,name']);
                $svg = view('torneos.public.share.partido', compact('tournament', 'match'))->render();
                return $this->svg($svg, $request, "futgo-{$tournament->slug}-partido-{$match->match_number}");
            }

            $match->load(['homeTeam:id,name,color', 'awayTeam:id,name,color', 'phase:id,name']);
            $this->png->generateForTournament($tournament, 'partido', ['match' => $match], $match->id);
        }

        return $this->servePng(
            $this->png->get($storagePath),
            "futgo-{$tournament->slug}-partido-{$match->match_number}",
        );
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    private function cardViewAndData(Tournament $tournament, string $card): array
    {
        return match ($card) {
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

    /** Respuesta PNG (inline o adjunto si ?descargar=1). */
    private function servePng(?string $data, string $filename): Response
    {
        if ($data === null) {
            abort(503, 'La imagen no pudo generarse.');
        }

        return response($data, 200, [
            'Content-Type'   => 'image/png',
            'Content-Length' => strlen($data),
            'Cache-Control'  => 'public, max-age=3600',
        ]);
    }
}
