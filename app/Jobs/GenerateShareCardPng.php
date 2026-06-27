<?php

namespace App\Jobs;

use App\Models\Social\FriendlyMatch;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\ShareCardPngService;
use App\Services\Torneos\TournamentReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Genera y almacena la versión PNG de una tarjeta compartible.
 *
 * Se encola para no bloquear el request HTTP. En entornos sin worker activo
 * (QUEUE_CONNECTION=sync) se ejecuta en el momento del dispatch.
 *
 * Tipos de tarjeta de torneo: goleadores | posiciones | mvp | partido
 * Tipo de amistoso: amistoso (usa $friendlyMatchId en vez de $tournamentId)
 */
class GenerateShareCardPng implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        /** Tipo: goleadores | posiciones | mvp | partido | amistoso */
        public readonly string $type,
        public readonly ?int $tournamentId = null,
        /** ID de partido de torneo (solo para tipo "partido"). */
        public readonly ?int $matchId = null,
        /** ID de FriendlyMatch (solo para tipo "amistoso"). */
        public readonly ?int $friendlyMatchId = null,
    ) {}

    public function handle(ShareCardPngService $png, TournamentReportService $report): void
    {
        if (! $png->gdAvailable()) {
            return;
        }

        if ($this->type === 'amistoso') {
            $match = FriendlyMatch::with(['homeClub', 'awayClub'])->find($this->friendlyMatchId);
            if ($match) {
                $png->generateForFriendly($match);
            }
            return;
        }

        $tournament = Tournament::find($this->tournamentId);
        if (! $tournament) {
            return;
        }

        $data = match ($this->type) {
            'goleadores' => ['scorers' => $report->topScorers($tournament, 8)],
            'posiciones' => ['phases'  => $report->groupStandings($tournament)],
            'mvp'        => ['match'   => $report->latestMvpMatch($tournament)],
            'partido'    => (function () use ($tournament) {
                $match = $tournament->matches()->with([
                    'homeTeam:id,name,color',
                    'awayTeam:id,name,color',
                    'phase:id,name',
                ])->find($this->matchId);
                return $match ? ['match' => $match] : null;
            })(),
            default => null,
        };

        if ($data === null) {
            return;
        }

        $id = $this->matchId ?? '';
        $png->generateForTournament($tournament, $this->type, $data, $id);
    }
}
