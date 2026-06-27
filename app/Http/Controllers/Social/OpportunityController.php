<?php

namespace App\Http\Controllers\Social;

use App\Exceptions\Social\OpportunityException;
use App\Http\Controllers\Controller;
use App\Models\Social\ContentReport;
use App\Models\Social\Opportunity;
use App\Models\Social\OpportunityResponse;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use App\Models\User;
use App\Rules\CleanText;
use App\Services\Social\OpportunityService;
use App\Services\Social\ReliabilityService;
use App\Services\Social\SuggestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * FutGO Social — Fase 1 · Oportunidades (Sesión S1-B).
 *
 * Publicar / explorar / responder / gestionar respuestas / cancelar / reportar.
 * La exploración (index, show) es pública para descubrimiento sin login; toda
 * acción (publicar, responder, etc.) exige cuenta — las rutas correspondientes
 * van bajo el middleware `auth` en web.php.
 */
class OpportunityController extends Controller
{
    /** Motivos de reporte ofrecidos en la UI. */
    private const REPORT_REASONS = ['spam', 'lenguaje_ofensivo', 'estafa', 'contenido_inapropiado', 'otro'];

    public function __construct(
        private OpportunityService $service,
        private ReliabilityService $reliability,
        private SuggestionService $suggestions,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Exploración (pública)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Listado/búsqueda con filtros por tipo, ciudad, nivel y fecha. El NIVEL es
     * un filtro obligatorio del resultado: por defecto solo muestra
     * oportunidades del nivel del que mira (o sin nivel), salvo que fuerce
     * "todos los niveles". Sin N+1: eager loading de creador/club + conteo.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $type  = $request->query('tipo');
        $city  = trim((string) $request->query('ciudad', ''));
        $level = $request->query('nivel');         // null=default · 'todos'=sin filtro
        $date  = $request->query('fecha');

        $query = Opportunity::query()
            ->active()
            ->visible()
            ->with([
                'user:id,name,futgo_id,avatar_url,play_level',
                'club:id,name,slug,shield_url,play_level',
            ])
            ->withCount('responses')
            ->latest();

        if ($type && in_array($type, Opportunity::TYPES, true)) {
            $query->where('type', $type);
        }

        if ($city !== '') {
            $query->where('city', 'like', '%' . $city . '%');
        }

        // Nivel: el filtro obligatorio. Default = nivel del que mira.
        $effectiveLevel = $this->resolveLevelFilter($level, $viewer);
        if ($effectiveLevel) {
            $query->where(function ($q) use ($effectiveLevel) {
                $q->whereNull('required_level')->orWhere('required_level', $effectiveLevel);
            });
        }

        if ($date) {
            $query->where('window_start', '>=', $date);
        }

        $opportunities = $query->paginate(12)->withQueryString();

        return view('social.oportunidades.index', [
            'opportunities'  => $opportunities,
            'types'          => Opportunity::TYPES,
            'levels'         => User::PLAY_LEVELS,
            'filters'        => compact('type', 'city', 'level', 'date'),
            'effectiveLevel' => $effectiveLevel,
            'viewer'         => $viewer,
        ]);
    }

    /** Ficha de una oportunidad. Pública; responder/gestionar requiere login. */
    public function show(Request $request, Opportunity $opportunity): View
    {
        abort_if($opportunity->is_hidden, 404);

        $opportunity->load([
            'user:id,name,futgo_id,avatar_url,play_level',
            'club:id,name,slug,shield_url,play_level,captain_user_id',
            'friendlyMatch',
            'responses' => fn ($q) => $q->latest(),
            'responses.user:id,name,futgo_id,avatar_url',
            'responses.club:id,name,slug,shield_url',
        ]);

        $user    = $request->user();
        $isOwner = $user ? $this->service->isOwner($opportunity, $user) : false;

        // Cancelable: abierta/en negociación, o cerrada con amistoso confirmado.
        $hasConfirmedFriendly = $opportunity->friendlyMatch
            && $opportunity->friendlyMatch->isConfirmado();
        $canCancel = $isOwner && (
            $opportunity->isAbierta() || $opportunity->isEnNegociacion()
            || ($opportunity->isCerrada() && $hasConfirmedFriendly)
        );

        // S3-A: clubs compatibles sugeridos al dueño de un BUSCAR_RIVAL abierto,
        // de forma proactiva (antes de que lleguen respuestas).
        $suggestedRivals = collect();
        if ($isOwner && $opportunity->isBuscarRival() && $opportunity->isAbierta()) {
            $suggestedRivals = $this->suggestions->compatibleRivalsFor($opportunity);
        }

        return view('social.oportunidades.show', [
            'opportunity'     => $opportunity,
            'isOwner'         => $isOwner,
            'canCancel'       => $canCancel,
            'canRespond'      => $user && ! $isOwner && $opportunity->canReceiveResponses(),
            'reportReasons'   => self::REPORT_REASONS,
            'myClubs'         => $user ? $this->captainedClubs($user->id) : collect(),
            'suggestedRivals' => $suggestedRivals,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Publicar
    // ─────────────────────────────────────────────────────────────────────

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($this->reliability->isPaused('user', $user->id)) {
            return redirect()->route('social.oportunidades.reactivar');
        }

        $reliabilityScore = ReliabilityScore::where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->first();

        // S2-A: "Retar"/"Invitar" desde la ficha de un jugador pre-completan el
        // formulario. `target` es el futgo_id del jugador destinatario. Se resuelve
        // a un usuario (columnas públicas) para mostrarlo y dejarlo en el payload.
        $targetPlayer = null;
        if ($targetId = $request->query('target')) {
            $targetPlayer = User::where('futgo_id', $targetId)
                ->where('id', '!=', $user->id)
                ->first(['id', 'name', 'futgo_id', 'play_level', 'city']);
        }

        return view('social.oportunidades.create', [
            'types'            => Opportunity::TYPES,
            'levels'           => User::PLAY_LEVELS,
            'myClubs'          => $this->captainedClubs($user->id),
            'preset'           => $request->query('tipo'),
            'userLevel'        => $user->play_level,
            'reliabilityScore' => $reliabilityScore,
            'targetPlayer'     => $targetPlayer,
            // Nivel/ciudad sugeridos: del destinatario si vino del perfil, si no del autor.
            'prefillLevel'     => $targetPlayer?->play_level ?? $user->play_level,
            'prefillCity'      => $targetPlayer?->city ?? $user->city,
        ]);
    }

    /**
     * S3-A · Modo rápido: formulario simplificado para "necesito rival para
     * mañana". Es siempre un BUSCAR_RIVAL express, con menos campos y todo
     * pre-completado con los datos del club y la disponibilidad más cercana.
     */
    public function createExpress(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($this->reliability->isPaused('user', $user->id)) {
            return redirect()->route('social.oportunidades.reactivar');
        }

        $myClubs = $this->captainedClubs($user->id);
        if ($myClubs->isEmpty()) {
            return redirect()
                ->route('social.oportunidades.create')
                ->with('error', 'Para usar el modo rápido necesitás capitanear un equipo.');
        }

        // Disponibilidad más cercana sugerida: mañana a las 20:00.
        $nearest = now()->addDay()->setTime(20, 0);

        return view('social.oportunidades.express', [
            'levels'       => User::PLAY_LEVELS,
            'myClubs'      => $myClubs,
            'prefillCity'  => $user->city,
            'prefillLevel' => $user->play_level,
            'nearest'      => $nearest,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $base = $request->validate([
            'type'           => ['required', Rule::in(Opportunity::TYPES)],
            'city'           => ['required', 'string', 'max:120'],
            'zone'           => ['nullable', 'string', 'max:120'],
            'required_level' => ['required', Rule::in(User::PLAY_LEVELS)],
            'descripcion'    => ['nullable', 'string', 'max:1000', new CleanText],
        ], [
            'required_level.required' => 'Declará el nivel: es el filtro que usa el matching.',
            'city.required'           => 'Indicá la ciudad.',
        ]);

        try {
            $data = $this->buildPublishData($request, $base);
            $opportunity = $this->service->publish($user, $data);
        } catch (OpportunityException $e) {
            if ($e->isPaused) {
                return redirect()->route('social.oportunidades.reactivar');
            }

            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('social.oportunidades.show', $opportunity)
            ->with('status', 'Oportunidad publicada. Ya podés recibir respuestas.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Mis oportunidades (publicadas + respondidas)
    // ─────────────────────────────────────────────────────────────────────

    public function mine(Request $request): View
    {
        $user      = $request->user();
        $myClubIds = $this->captainedClubs($user->id)->pluck('id');

        $published = Opportunity::query()
            ->where(function ($q) use ($user, $myClubIds) {
                $q->where('user_id', $user->id)->orWhereIn('club_id', $myClubIds);
            })
            ->with(['club:id,name,slug,shield_url'])
            ->withCount([
                'responses',
                'responses as pending_responses_count' => fn ($q) => $q->where('status', OpportunityResponse::STATUS_PENDIENTE),
            ])
            ->latest()
            ->get();

        $myResponses = OpportunityResponse::query()
            ->where('user_id', $user->id)
            ->orWhereIn('club_id', $myClubIds)
            ->with(['opportunity:id,type,city,status,user_id,club_id', 'opportunity.club:id,name'])
            ->latest()
            ->get();

        return view('social.oportunidades.mine', compact('published', 'myResponses'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reactivación de disponibilidad (S1-D)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Pantalla de confirmación: explica la pausa y pide que el usuario reconozca
     * el problema antes de reactivarse. También se usa como redirect cuando se
     * intenta publicar y la cuenta está pausada.
     */
    public function reactivar(Request $request): View
    {
        $user  = $request->user();
        $score = ReliabilityScore::where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->first();

        return view('social.oportunidades.reactivar', [
            'score'   => $score,
            'isPaused' => $score?->is_paused ?? false,
        ]);
    }

    /** Procesa la confirmación: reactiva al usuario y/o su club activo. */
    public function confirmarReactivacion(Request $request): RedirectResponse
    {
        $request->validate([
            'acknowledged' => ['required', 'accepted'],
        ], [
            'acknowledged.required' => 'Tenés que reconocer el problema para poder reactivarte.',
            'acknowledged.accepted'  => 'Tenés que marcar la casilla de confirmación.',
        ]);

        $user = $request->user();

        $this->reliability->reactivate('user', $user->id);

        // Reactiva también los clubs de los que el usuario es capitán.
        $captainClubIds = Club::where('captain_user_id', $user->id)->pluck('id');
        foreach ($captainClubIds as $clubId) {
            $this->reliability->reactivate('club', $clubId);
        }

        return redirect()
            ->route('social.oportunidades.create')
            ->with('status', 'Tu disponibilidad fue reactivada. Ahora podés publicar oportunidades.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Responder
    // ─────────────────────────────────────────────────────────────────────

    public function respond(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500', new CleanText],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
        ]);

        try {
            $this->service->respond($opportunity, $user, $data);
        } catch (OpportunityException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Respuesta enviada. El creador la verá en su panel.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Gestión de respuestas recibidas (solo el creador)
    // ─────────────────────────────────────────────────────────────────────

    public function acceptResponse(Request $request, OpportunityResponse $response): RedirectResponse
    {
        $this->authorizeOwnerOfResponse($request, $response);

        try {
            $this->service->accept($response);
        } catch (OpportunityException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Respuesta aceptada.');
    }

    public function rejectResponse(Request $request, OpportunityResponse $response): RedirectResponse
    {
        $this->authorizeOwnerOfResponse($request, $response);

        $this->service->reject($response);

        return back()->with('status', 'Respuesta rechazada.');
    }

    public function counterResponse(Request $request, OpportunityResponse $response): RedirectResponse
    {
        $this->authorizeOwnerOfResponse($request, $response);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500', new CleanText],
        ]);

        $this->service->counter($response, $data['message'] ?? null);

        return back()->with('status', 'Contrapropuesta enviada.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cancelar
    // ─────────────────────────────────────────────────────────────────────

    public function cancel(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->service->isOwner($opportunity, $user), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255', new CleanText],
        ], [
            'reason.required' => 'Indicá el motivo de la cancelación.',
        ]);

        try {
            $this->service->cancel($opportunity, $data['reason']);
        } catch (OpportunityException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Oportunidad cancelada.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reportar contenido
    // ─────────────────────────────────────────────────────────────────────

    public function report(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'reason'  => ['required', Rule::in(self::REPORT_REASONS)],
            'details' => ['nullable', 'string', 'max:500', new CleanText],
        ], [
            'reason.required' => 'Elegí un motivo.',
        ]);

        // Evita reportes duplicados del mismo usuario sobre la misma oportunidad.
        $exists = ContentReport::where('reporter_user_id', $user->id)
            ->where('reportable_type', 'opportunity')
            ->where('reportable_id', $opportunity->id)
            ->where('status', ContentReport::STATUS_PENDIENTE)
            ->exists();

        if (! $exists) {
            ContentReport::create([
                'reporter_user_id' => $user->id,
                'reportable_type'  => 'opportunity',
                'reportable_id'    => $opportunity->id,
                'reason'           => $data['reason'],
                'details'          => $data['details'] ?? null,
            ]);
        }

        return back()->with('status', 'Gracias. Un administrador revisará el reporte.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /** Arma el payload tipado según el tipo de oportunidad. */
    private function buildPublishData(Request $request, array $base): array
    {
        $payload = [];
        if (! empty($base['descripcion'])) {
            $payload['descripcion'] = $base['descripcion'];
        }

        // S2-A: oportunidad dirigida a un jugador concreto (retar/invitar desde su
        // ficha). Se registra en el payload sin alterar el flujo: cualquiera puede
        // responder, pero queda claro a quién se apuntó la invitación.
        $directed = $request->validate([
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if (! empty($directed['target_user_id'])) {
            $target = User::find($directed['target_user_id']);
            if ($target) {
                $payload['directed_to_user_id'] = $target->id;
                $payload['directed_to_name']    = $target->name;
                $payload['directed_to_futgo_id'] = $target->futgo_id;
            }
        }

        $data = [
            'type'           => $base['type'],
            'city'           => $base['city'],
            'zone'           => $base['zone'] ?? null,
            'required_level' => $base['required_level'],
            'window_start'   => null,
            'window_end'     => null,
            'club_id'        => null,
            'is_express'     => $request->boolean('is_express'),
        ];

        switch ($base['type']) {
            case Opportunity::TYPE_BUSCAR_RIVAL:
                $extra = $request->validate([
                    'club_id'          => ['required', 'integer', 'exists:clubs,id'],
                    'window_start'     => ['required', 'date'],
                    'venue_id'         => ['nullable', 'integer', 'exists:venues,id'],
                    'cancha_propuesta' => ['nullable', 'string', 'max:255'],
                ]);
                $data['club_id']      = $extra['club_id'];
                $data['window_start'] = $extra['window_start'];
                $data['venue_id']     = $extra['venue_id'] ?? null;
                if (! empty($extra['cancha_propuesta'])) {
                    $payload['cancha_propuesta'] = $extra['cancha_propuesta'];
                }
                break;

            case Opportunity::TYPE_BUSCAR_JUGADOR:
                $extra = $request->validate([
                    'club_id'    => ['required', 'integer', 'exists:clubs,id'],
                    'posiciones' => ['nullable', 'string', 'max:120'],
                    'cupos'      => ['required', 'integer', 'min:1', 'max:30'],
                ]);
                $data['club_id']        = $extra['club_id'];
                $payload['posiciones']  = $extra['posiciones'] ?? null;
                $payload['cupos']       = (int) $extra['cupos'];
                break;

            case Opportunity::TYPE_BUSCAR_REFUERZO:
                $extra = $request->validate([
                    'club_id'      => ['required', 'integer', 'exists:clubs,id'],
                    'partido'      => ['required', 'string', 'max:255'],
                    'posicion'     => ['nullable', 'string', 'max:60'],
                    'window_start' => ['nullable', 'date'],
                ]);
                $data['club_id']      = $extra['club_id'];
                $data['window_start'] = $extra['window_start'] ?? null;
                $payload['partido']   = $extra['partido'];
                $payload['posicion']  = $extra['posicion'] ?? null;
                break;

            case Opportunity::TYPE_BUSCAR_EQUIPO:
                $extra = $request->validate([
                    'posicion'       => ['required', 'string', 'max:60'],
                    'disponibilidad' => ['nullable', 'string', 'max:255'],
                ]);
                $payload['posicion']       = $extra['posicion'];
                $payload['disponibilidad'] = $extra['disponibilidad'] ?? null;
                break;
        }

        // S3-A · Modo rápido: vigencia corta que termina en la fecha propuesta —
        // así la oportunidad vence sola al llegar el partido (sin lógica extra).
        if ($data['is_express'] && $data['window_start']) {
            $data['expires_at'] = $data['window_start'];
        }

        $data['payload'] = $payload;

        return $data;
    }

    /** Resuelve el nivel efectivo del filtro de exploración. */
    private function resolveLevelFilter(?string $param, $viewer): ?string
    {
        if ($param === 'todos') {
            return null;
        }
        if ($param && in_array($param, User::PLAY_LEVELS, true)) {
            return $param;
        }
        if ($viewer && $viewer->hasPlayLevel()) {
            return $viewer->play_level;
        }

        return null;
    }

    /** Clubs que el usuario capitanea (para actuar en su nombre). */
    private function captainedClubs(int $userId)
    {
        return Club::where('captain_user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'shield_url', 'play_level']);
    }

    /** Autoriza al creador de la oportunidad a gestionar una de sus respuestas. */
    private function authorizeOwnerOfResponse(Request $request, OpportunityResponse $response): void
    {
        $opportunity = $response->opportunity;
        abort_if($opportunity === null, 404);
        abort_unless($this->service->isOwner($opportunity, $request->user()), 403);
    }
}
