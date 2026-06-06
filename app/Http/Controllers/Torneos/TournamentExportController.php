<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\TournamentReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación PDF/CSV del torneo (Sesión E): resultados, posiciones y estadísticas.
 *
 * Reutiliza el patrón de la auditoría de la polla: dompdf para PDF, CSV con BOM
 * UTF-8 (para que Excel respete los acentos). Disponible para el ADMIN del torneo
 * (con scoping) y, si el torneo es público, también desde el portal público.
 *
 * Los datasets son deportivos (no contienen email/teléfono/documento).
 */
class TournamentExportController extends Controller
{
    private const DATASETS = ['resultados', 'posiciones', 'estadisticas'];
    private const FORMATS  = ['pdf', 'csv'];

    public function __construct(private TournamentReportService $report) {}

    /** Descarga desde el panel admin del torneo (requiere gestionar el torneo). */
    public function admin(Tournament $tournament, string $dataset, string $format): Response|StreamedResponse
    {
        $this->authorizeManage($tournament);

        return $this->download($tournament, $dataset, $format);
    }

    /** Descarga desde el portal público (solo torneos públicos). */
    public function public(Tournament $tournament, string $dataset, string $format): Response|StreamedResponse
    {
        abort_unless($tournament->isPublic(), 404);

        return $this->download($tournament, $dataset, $format);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function download(Tournament $tournament, string $dataset, string $format): Response|StreamedResponse
    {
        abort_unless(in_array($dataset, self::DATASETS, true), 404);
        abort_unless(in_array($format, self::FORMATS, true), 404);

        [$title, $headers, $rows] = $this->dataset($tournament, $dataset);

        $base = 'FutGO_' . \Illuminate\Support\Str::slug($tournament->name) . '_' . $dataset . '_' . now()->format('Y-m-d');

        return $format === 'csv'
            ? $this->csv($base, $headers, $rows)
            : $this->pdf($base, $tournament, $title, $headers, $rows);
    }

    /** @return array{0:string,1:array<int,string>,2:array<int,array<int,string|int>>} */
    private function dataset(Tournament $tournament, string $dataset): array
    {
        return match ($dataset) {
            'resultados'   => $this->resultados($tournament),
            'posiciones'   => $this->posiciones($tournament),
            'estadisticas' => $this->estadisticas($tournament),
        };
    }

    private function resultados(Tournament $tournament): array
    {
        $rows = $this->report->finishedMatches($tournament)->map(fn ($m) => [
            $m->phase?->name ?? '—',
            $m->scheduled_at ? Carbon::parse($m->scheduled_at)->locale('es')->isoFormat('D MMM YYYY HH:mm') : 'Sin fecha',
            $m->homeTeam?->name ?? 'Por definir',
            "{$m->home_score} - {$m->away_score}" . ($m->isWalkover() ? ' (W.O.)' : ''),
            $m->awayTeam?->name ?? 'Por definir',
            $m->venue ?? '—',
        ])->all();

        return ['Resultados', ['Fase', 'Fecha', 'Local', 'Marcador', 'Visitante', 'Cancha'], $rows];
    }

    private function posiciones(Tournament $tournament): array
    {
        $rows = [];
        foreach ($this->report->groupStandings($tournament) as $phase) {
            foreach ($phase->groups as $group) {
                foreach ($group->standings as $s) {
                    $rows[] = [
                        $group->name,
                        $s->position,
                        $s->team?->name ?? '—',
                        $s->played, $s->won, $s->drawn, $s->lost,
                        $s->goals_for, $s->goals_against, $s->goal_difference,
                        $s->points,
                    ];
                }
            }
        }

        return ['Tabla de posiciones',
            ['Grupo', 'Pos', 'Equipo', 'PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'Pts'],
            $rows];
    }

    private function estadisticas(Tournament $tournament): array
    {
        $rows = $this->report->playerStats($tournament)->map(fn ($st) => [
            $st->teamPlayer?->displayName() ?? '—',
            $st->teamPlayer?->team?->name ?? '—',
            $st->matches_played,
            $st->goals,
            $st->assists,
            $st->yellow_cards,
            $st->red_cards,
            $st->mvps,
        ])->all();

        return ['Estadísticas de jugadores',
            ['Jugador', 'Equipo', 'PJ', 'Goles', 'Asist.', 'Amar.', 'Rojas', 'MVP'],
            $rows];
    }

    private function csv(string $base, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");          // BOM UTF-8 para Excel
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "{$base}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function pdf(string $base, Tournament $tournament, string $title, array $headers, array $rows): Response
    {
        $pdf = Pdf::loadView('torneos.export.pdf', [
            'tournament'  => $tournament,
            'title'       => $title,
            'headers'     => $headers,
            'rows'        => $rows,
            'generatedAt' => now()->locale('es')->isoFormat('dddd D [de] MMMM YYYY [a las] HH:mm'),
        ])->setPaper('letter', count($headers) > 7 ? 'landscape' : 'portrait');

        return $pdf->download("{$base}.pdf");
    }

    private function authorizeManage(Tournament $tournament): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $tournament->tournamentAdmins()->where('user_id', $user->id)->exists(),
            403
        );
    }
}
