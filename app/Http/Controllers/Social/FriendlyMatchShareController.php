<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\FriendlyMatch;
use App\Services\Torneos\ShareCardPngService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tarjetas compartibles de amistosos FutGO Social.
 *
 * SVG: renderizado desde Blade (sin dependencias).
 * PNG: generado con GD y almacenado en caché; degrada a SVG si GD no está disponible.
 *
 * Solo los amistosos en estado "jugado" pueden tener tarjeta (resultado confirmado).
 */
class FriendlyMatchShareController extends Controller
{
    public function __construct(private ShareCardPngService $png) {}

    /** Tarjeta SVG del resultado del amistoso. Pública: cualquiera con el ID puede verla. */
    public function card(Request $request, FriendlyMatch $friendlyMatch): Response
    {
        abort_unless($friendlyMatch->status === FriendlyMatch::STATUS_JUGADO, 404);

        $friendlyMatch->load(['homeClub', 'awayClub', 'venue']);
        $svg = view('social.share.amistoso', ['match' => $friendlyMatch])->render();

        return $this->svg($svg, $request, "futgo-amistoso-{$friendlyMatch->id}");
    }

    /** Tarjeta PNG del resultado del amistoso. Genera en el momento si no está en caché. */
    public function cardPng(Request $request, FriendlyMatch $friendlyMatch): Response
    {
        abort_unless($friendlyMatch->status === FriendlyMatch::STATUS_JUGADO, 404);

        $storagePath = $this->png->friendlyStoragePath($friendlyMatch->id);

        if (! $this->png->exists($storagePath)) {
            if (! $this->png->gdAvailable()) {
                $friendlyMatch->load(['homeClub', 'awayClub', 'venue']);
                $svg = view('social.share.amistoso', ['match' => $friendlyMatch])->render();
                return $this->svg($svg, $request, "futgo-amistoso-{$friendlyMatch->id}");
            }

            $friendlyMatch->load(['homeClub', 'awayClub', 'venue']);
            $this->png->generateForFriendly($friendlyMatch);
        }

        $data = $this->png->get($storagePath);
        if ($data === null) {
            abort(503);
        }

        return response($data, 200, [
            'Content-Type'   => 'image/png',
            'Content-Length' => strlen($data),
            'Cache-Control'  => 'public, max-age=3600',
        ]);
    }

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
