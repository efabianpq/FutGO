<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Services\Privacy\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Derecho al olvido (flujo en pasos).
 *
 * 1) confirmar con contraseña → 2) código por email → 3) verificar → periodo de
 * gracia (cancelable) → ejecución automática (comando programado).
 */
class AccountDeletionController extends Controller
{
    public function __construct(private AccountDeletionService $deletion)
    {
    }

    public function show(Request $request): View
    {
        $pending = $this->deletion->pendingRequest($request->user());

        return view('privacy.center.delete', [
            'pending' => $pending,
        ]);
    }

    /** Paso 1: confirma contraseña y envía el código. */
    public function request(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm'  => ['accepted'],
        ], [
            'password.current_password' => 'La contraseña no es correcta.',
            'confirm.accepted'          => 'Debes confirmar que entiendes que la acción es irreversible.',
        ]);

        $this->deletion->requestDeletion($request->user(), $request);

        return redirect()->route('privacidad.eliminar')
            ->with('status', 'Te enviamos un código de confirmación a tu correo.');
    }

    /** Paso 3: verifica el código y arranca el periodo de gracia. */
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $pending = $this->deletion->pendingRequest($request->user());

        if ($pending === null || ! $this->deletion->verify($pending, $data['code'])) {
            return back()->withErrors(['code' => 'El código no es válido o expiró.']);
        }

        \App\Services\Privacy\AuditLogger::record('account_deletion_requested', $request->user());

        $days = (int) config('privacy.deletion_grace_days', 30);

        return redirect()->route('privacidad.eliminar')
            ->with('status', "Tu cuenta se eliminará en {$days} días. Puedes cancelar hasta entonces.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->deletion->cancel($request->user());

        return redirect()->route('privacidad.centro')
            ->with('status', 'Cancelaste la eliminación de tu cuenta.');
    }
}
