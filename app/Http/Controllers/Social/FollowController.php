<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Services\Social\FollowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FutGO Social — Fase 1 · Sesión S1-E · seguir / dejar de seguir.
 *
 * Un único endpoint TOGGLE desde el perfil de la entidad (club, jugador, torneo).
 * Sin pantallas propias: vuelve atrás con el estado actualizado.
 */
class FollowController extends Controller
{
    public function __construct(private FollowService $service) {}

    /** Alterna el seguimiento de la entidad indicada por {type}/{id}. */
    public function toggle(Request $request, string $type, int $id): RedirectResponse
    {
        $followable = $this->resolve($type, $id);

        $nowFollowing = $this->service->toggle($request->user(), $followable);

        return back()->with('status', $nowFollowing ? 'Ahora seguís esta página.' : 'Dejaste de seguir.');
    }

    /** Resuelve el modelo seguible desde el alias de tipo + id (404 si no existe). */
    private function resolve(string $type, int $id): Model
    {
        $class = FollowService::FOLLOWABLE[$type] ?? null;
        abort_if($class === null, 404);

        return $class::findOrFail($id);
    }
}
