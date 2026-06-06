<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\CredentialValidation;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Services\Torneos\CredentialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Validación de credenciales por árbitros/admin (/torneos/validar) — Sesión D.
 *
 * Dos caminos hacia el mismo resultado (degrada con gracia ante mala conectividad):
 *  - QR: el árbitro escanea la credencial → abre GET /torneos/validar?fg=..&sig=..
 *  - Manual: el árbitro escribe el identificador FUTGO en el formulario (POST).
 *
 * Cada validación queda registrada (auditoría) en credential_validations.
 */
class CredentialValidationController extends Controller
{
    /** Formulario + (si llega ?fg= desde un QR) ejecuta la validación. */
    public function index(Request $request): View
    {
        $result = null;

        if ($request->filled('fg')) {
            $signatureValid = CredentialService::verify(
                (string) $request->query('fg'),
                $request->query('sig')
            );

            $result = $this->performValidation(
                (string) $request->query('fg'),
                $request->query('tournament_id'),
                'qr',
                $signatureValid
            );
        }

        return view('torneos.credencial.validar', [
            'tournaments' => $this->officiableTournaments(),
            'result'      => $result,
            'queriedFg'   => $request->query('fg'),
        ]);
    }

    /** Validación por ingreso manual del identificador (sin cámara). */
    public function validate(Request $request): View
    {
        $data = $request->validate([
            'fg'            => ['required', 'string', 'max:20'],
            'tournament_id' => ['nullable', 'integer', 'exists:tournaments,id'],
        ], [
            'fg.required' => 'Ingresá el identificador FUTGO del jugador.',
        ]);

        $result = $this->performValidation(
            $data['fg'],
            $data['tournament_id'] ?? null,
            'manual',
            // El ingreso manual no requiere firma: la verificación es contra el backend.
            false
        );

        return view('torneos.credencial.validar', [
            'tournaments' => $this->officiableTournaments(),
            'result'      => $result,
            'queriedFg'   => $data['fg'],
        ]);
    }

    /**
     * Núcleo de la validación. Resuelve el jugador por identificador, decide si
     * está habilitado para el torneo (si se indicó) y registra la auditoría.
     *
     * @return array{result:string,user:?User,tournament:?Tournament,memberships:\Illuminate\Support\Collection,method:string,signatureValid:bool,futgo_id:string}
     */
    private function performValidation(string $futgoId, $tournamentId, string $method, bool $signatureValid): array
    {
        $futgoId = strtoupper(trim($futgoId));

        $user = User::where('futgo_id', $futgoId)->first();
        $tournament = $tournamentId ? Tournament::find($tournamentId) : null;

        $memberships = collect();
        $result = 'no_encontrado';

        if ($user) {
            // Inscripciones ACTIVAS del jugador (titular/suplente habilitado).
            $active = TeamPlayer::where('user_id', $user->id)
                ->where('status', 'active')
                ->with(['team.tournament', 'team.club'])
                ->get();

            if ($tournament) {
                $teamIds = $tournament->teams()->pluck('id');
                $memberships = $active->whereIn('team_id', $teamIds)->values();
                $habilitado = $memberships->isNotEmpty()
                    && ! in_array($tournament->status, ['finished', 'cancelled'], true);
            } else {
                // Sin torneo: habilitado si participa activo en algún torneo vigente.
                $memberships = $active
                    ->filter(fn ($tp) => $tp->team?->tournament
                        && ! in_array($tp->team->tournament->status, ['finished', 'cancelled'], true))
                    ->values();
                $habilitado = $memberships->isNotEmpty();
            }

            $result = $habilitado ? 'habilitado' : 'no_habilitado';
        }

        CredentialValidation::create([
            'futgo_id'             => $futgoId,
            'validated_user_id'    => $user?->id,
            'validated_by_user_id' => auth()->id(),
            'tournament_id'        => $tournament?->id,
            'result'               => $result,
            'method'               => $method,
        ]);

        return [
            'result'         => $result,
            'user'           => $user,
            'tournament'     => $tournament,
            'memberships'    => $memberships,
            'method'         => $method,
            'signatureValid' => $signatureValid,
            'futgo_id'       => $futgoId,
        ];
    }

    /** Torneos que el usuario puede arbitrar (los suyos; todos si es admin global). */
    private function officiableTournaments()
    {
        $user = auth()->user();

        return Tournament::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas(
                'tournamentAdmins',
                fn ($a) => $a->where('user_id', $user->id)
            ))
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'status']);
    }
}
