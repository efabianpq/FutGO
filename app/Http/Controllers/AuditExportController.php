<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExportController extends Controller
{
    private const PHASE_LABELS = [
        'grupos' => 'Fase de Grupos',
        'dieciseisavos' => 'Dieciseisavos',
        'octavos' => 'Octavos',
        'cuartos' => 'Cuartos',
        'semifinal' => 'Semifinales',
        '3er_puesto' => 'Tercer Puesto',
        'final' => 'Final',
    ];

    /**
     * Página con los botones de descarga.
     */
    public function index(Request $request): View
    {
        $isAdmin = $request->user()->isAdmin();
        $totalRows = $this->buildQuery()->count();

        return view('audit.index', [
            'totalRows' => $totalRows,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Descarga del CSV (UTF-8 con BOM para Excel).
     */
    public function csv(): StreamedResponse
    {
        $filename = 'SoyPachonMundial_Auditoria_' . now()->format('Y-m-d') . '.csv';
        $rows = $this->fetchRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel detecte los acentos
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Partido', 'Fase', 'Fecha', 'Resultado oficial',
                'Usuario', 'Pronóstico', 'Puntos',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['match'],
                    $r['phase'],
                    $r['date'],
                    $r['official'],
                    $r['user'],
                    $r['prediction'],
                    $r['points'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Descarga del PDF (landscape, fuente compacta, logo).
     */
    public function pdf(): Response
    {
        $filename = 'SoyPachonMundial_Auditoria_' . now()->format('Y-m-d') . '.pdf';
        $rows = $this->fetchRows();

        $pdf = Pdf::loadView('audit.pdf', [
            'rows' => $rows,
            'generatedAt' => now()->locale('es')->isoFormat('dddd D [de] MMMM YYYY [a las] HH:mm'),
            'totalRows' => count($rows),
        ])->setPaper('letter', 'landscape');

        return $pdf->download($filename);
    }

    private function buildQuery()
    {
        return DB::table('predictions')
            ->join('matches', 'matches.id', '=', 'predictions.match_id')
            ->join('users', 'users.id', '=', 'predictions.user_id')
            ->where('matches.status', 'finished')
            ->whereNotNull('predictions.points_earned')
            ->where('users.is_active', true);
    }

    /** @return array<int, array<string,string|int>> */
    private function fetchRows(): array
    {
        $query = $this->buildQuery()
            ->orderBy('matches.match_datetime')
            ->orderByDesc('predictions.points_earned')
            ->orderBy('users.name')
            ->select([
                'matches.match_number',
                'matches.phase',
                'matches.home_team',
                'matches.away_team',
                'matches.home_flag',
                'matches.away_flag',
                'matches.home_score_official',
                'matches.away_score_official',
                'matches.match_datetime',
                'users.name as user_name',
                'predictions.home_score',
                'predictions.away_score',
                'predictions.points_earned',
            ]);

        return $query->get()->map(function ($r) {
            return [
                'match' => trim(($r->home_flag ?? '') . ' ' . $r->home_team . ' vs ' . ($r->away_flag ?? '') . ' ' . $r->away_team),
                'phase' => self::PHASE_LABELS[$r->phase] ?? $r->phase,
                'date' => \Carbon\Carbon::parse($r->match_datetime)->locale('es')->isoFormat('D MMM YYYY HH:mm'),
                'official' => "{$r->home_score_official} - {$r->away_score_official}",
                'user' => $r->user_name,
                'prediction' => "{$r->home_score} - {$r->away_score}",
                'points' => (int) $r->points_earned,
            ];
        })->all();
    }
}
