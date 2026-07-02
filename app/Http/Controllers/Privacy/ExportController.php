<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\DataRequest;
use App\Services\Privacy\PrivacyExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Centro de Privacidad · Portabilidad (descarga de datos) + habeas data.
 */
class ExportController extends Controller
{
    public function __construct(private PrivacyExportService $export)
    {
    }

    public function show(): View
    {
        return view('privacy.center.export');
    }

    /** Genera y descarga un JSON con los datos del usuario. */
    public function download(Request $request): SymfonyResponse
    {
        $user = $request->user();

        $data = $this->export->build($user);

        DataRequest::create([
            'user_id'      => $user->id,
            'type'         => DataRequest::TYPE_EXPORT,
            'status'       => DataRequest::STATUS_COMPLETED,
            'requested_ip' => $request->ip(),
            'completed_at' => now(),
        ]);

        \App\Services\Privacy\AuditLogger::record('data_exported', $user);

        $filename = 'futgo-datos-'.$user->id.'-'.now()->format('Ymd').'.json';

        return response()->json($data, Response::HTTP_OK, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Página informativa de derechos habeas data (consulta/actualización/corrección/supresión). */
    public function habeasData(): View
    {
        return view('privacy.center.habeas');
    }
}
