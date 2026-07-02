<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Historial de actividad (audit_logs del propio usuario).
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $logs = $request->user()
            ->auditLogs()
            ->latest()
            ->paginate(20);

        return view('privacy.center.activity', [
            'logs' => $logs,
        ]);
    }
}
