<?php

namespace App\Http\Controllers\Admin\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\ContentReport;
use App\Services\Social\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FutGO Social — S1-F · Panel de moderación (admin).
 *
 * Lista los content_reports pendientes y permite resolverlos con tres acciones:
 * desestimar, ocultar el contenido o suspender al usuario responsable.
 * Toda decisión queda registrada con quién la tomó y cuándo.
 */
class ModerationController extends Controller
{
    public function __construct(private ModerationService $service) {}

    /** Bandeja de reportes pendientes + historial reciente de resueltos. */
    public function index(): View
    {
        $pending = ContentReport::pendientes()
            ->with(['reporter', 'reportable', 'reviewer'])
            ->orderBy('created_at')
            ->get();

        $resolved = ContentReport::resueltos()
            ->with(['reporter', 'reportable', 'reviewer'])
            ->orderByDesc('reviewed_at')
            ->limit(30)
            ->get();

        return view('admin.social.moderacion', compact('pending', 'resolved'));
    }

    /** Procesa la acción del admin sobre un reporte. */
    public function resolve(Request $request, ContentReport $report): RedirectResponse
    {
        $data = $request->validate([
            'action'         => ['required', 'in:' . implode(',', ContentReport::ACTIONS)],
            'admin_notes'    => ['nullable', 'string', 'max:500'],
            'suspend_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'suspend_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($report->isResuelto()) {
            return back()->with('error', 'Este reporte ya fue resuelto.');
        }

        $this->service->resolveReport(
            report:        $report,
            admin:         $request->user(),
            action:        $data['action'],
            notes:         $data['admin_notes'] ?? null,
            suspendDays:   isset($data['suspend_days']) ? (int) $data['suspend_days'] : null,
            suspendReason: $data['suspend_reason'] ?? null,
        );

        $labels = [
            'dismissed' => 'Reporte desestimado.',
            'hidden'    => 'Contenido ocultado del listado público.',
            'suspended' => 'Usuario suspendido.',
        ];

        return back()->with('status', $labels[$data['action']] ?? 'Reporte resuelto.');
    }
}
