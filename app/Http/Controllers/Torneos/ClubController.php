<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use App\Services\Torneos\ClubMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Equipo PERMANENTE (club): creación transversal, plantilla permanente, escudo,
 * cambio de capitán y perfil histórico. La identidad NO depende de un torneo.
 */
class ClubController extends Controller
{
    public function __construct(
        private ClubMembershipService $membership,
        private \App\Services\Torneos\ClubStatsService $clubStats,
    ) {}

    /** Crea un equipo permanente; el creador queda como capitán y primer jugador. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'min:3', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.required' => 'Indica el nombre del equipo.',
            'color.regex'   => 'El color debe estar en formato hexadecimal (#RRGGBB).',
        ]);

        // Un usuario no puede tener dos equipos con el mismo nombre.
        $dup = Club::where('captain_user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($data['name']))])
            ->exists();
        if ($dup) {
            return back()->withErrors(['name' => 'Ya tienes un equipo con ese nombre.'])->withInput();
        }

        $club = Club::create([
            'name'               => $data['name'],
            'slug'               => $this->uniqueSlug($data['name']),
            'color'              => $data['color'] ?? null,
            'created_by_user_id' => $user->id,
            'captain_user_id'    => $user->id,
        ]);

        ClubPlayer::create([
            'club_id'             => $club->id,
            'user_id'             => $user->id,
            'is_captain'          => true,
            'verification_status' => 'registrado',
            'status'              => 'active',
        ]);

        \App\Services\Privacy\AuditLogger::record('team_created', $user, $club, ['name' => $club->name]);

        return redirect()
            ->route('torneos.clubes.manage', $club)
            ->with('status', 'Equipo creado. Ya eres su capitán.');
    }

    /** Panel de gestión de la plantilla permanente (solo capitán/admin). */
    public function manage(Club $club): View
    {
        $this->authorizeManage($club);

        $club->load(['players.user', 'captain']);
        $players = $club->players->sortByDesc('is_captain');

        // Candidatos a capitán: miembros registrados que no son el capitán actual.
        $captainCandidates = $club->players
            ->filter(fn ($p) => $p->user_id && ! $p->is_captain && $p->isRegistered())
            ->values();

        $lockedForEdit = $club->isInActiveTournament();

        return view('torneos.clubes.manage', compact('club', 'players', 'captainCandidates', 'lockedForEdit'));
    }

    /** Cambia nombre/color del equipo (solo si no participa en torneo activo). */
    public function update(Request $request, Club $club): RedirectResponse
    {
        $this->authorizeManage($club);

        if ($club->isInActiveTournament()) {
            return back()->with('error', 'No se puede editar el equipo mientras participa en un torneo activo.');
        }

        $data = $request->validate([
            'name'  => ['required', 'string', 'min:3', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'color.regex' => 'El color debe estar en formato hexadecimal (#RRGGBB).',
        ]);

        $club->update(['name' => $data['name'], 'color' => $data['color'] ?? null]);

        return back()->with('status', 'Datos del equipo actualizados.');
    }

    /** Sube/actualiza el escudo del equipo (solo si no participa en torneo activo). */
    public function updateShield(Request $request, Club $club): RedirectResponse
    {
        $this->authorizeManage($club);

        if ($club->isInActiveTournament()) {
            return back()->with('error', 'No se puede cambiar el escudo mientras el equipo participa en un torneo activo.');
        }

        $request->validate([
            'shield' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'shield.required' => 'Seleccioná una imagen.',
            'shield.image'    => 'El archivo debe ser una imagen.',
            'shield.mimes'    => 'Formatos permitidos: JPG, PNG o WEBP.',
            'shield.max'      => 'La imagen no puede superar los 2 MB.',
        ]);

        $this->deleteFromMediaDisk($club->shield_url);

        $disk = config('filesystems.media_disk', 'public');
        $path = $request->file('shield')->store('clubs', $disk);
        $club->update(['shield_url' => Storage::disk($disk)->url($path)]);

        return back()->with('status', 'Escudo del equipo actualizado.');
    }

    /**
     * Búsqueda de jugadores registrados por nombre (autocompletado).
     * Devuelve coincidencias parciales para que el capitán encuentre al usuario
     * sin saber su email exacto (E6/H9). No expone datos sensibles más allá del
     * nombre y un email parcialmente enmascarado para distinguir homónimos.
     */
    public function searchPlayers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = User::query()
            ->where('name', 'like', '%' . $q . '%')
            ->whereNotNull('futgo_id')           // jugadores del ecosistema
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $this->maskEmail($u->email),
            ]);

        return response()->json($results);
    }

    /** Enmascara el email para distinguir homónimos sin exponerlo completo. */
    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '';
        }
        [$local, $domain] = explode('@', $email, 2);
        $shown = mb_substr($local, 0, 2);
        return $shown . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain;
    }

    /** Agrega un jugador registrado (por nombre/sugerencia o email) a la plantilla. */
    public function addPlayer(Request $request, Club $club): RedirectResponse
    {
        $this->authorizeManage($club);

        $data = $request->validate([
            'user_id'       => ['nullable', 'integer', 'exists:users,id'],
            'email'         => ['nullable', 'email'],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'position'      => ['nullable', 'string', 'max:30'],
        ]);

        // Preferimos el user_id elegido en el autocompletado; si no, el email.
        $player = null;
        if (! empty($data['user_id'])) {
            $player = User::find($data['user_id']);
        } elseif (! empty($data['email'])) {
            $player = User::where('email', $data['email'])->first();
        }

        if (! $player) {
            return back()->withErrors(['user_id' => 'Elige un jugador de la lista de sugerencias o ingresa un email válido.']);
        }

        if ($club->players()->where('user_id', $player->id)->exists()) {
            return back()->withErrors(['user_id' => "{$player->name} ya está en la plantilla."]);
        }

        $member = ClubPlayer::create([
            'club_id'             => $club->id,
            'user_id'             => $player->id,
            'verification_status' => 'registrado',
            'jersey_number'       => $data['jersey_number'] ?? null,
            'position'            => $data['position'] ?? null,
            'status'              => 'active',
        ]);

        $this->membership->syncMemberAdded($member);

        return back()->with('status', "{$player->name} agregado a la plantilla.");
    }

    /** Agrega un jugador NO registrado (por verificar) a la plantilla permanente. */
    public function addGuestPlayer(Request $request, Club $club): RedirectResponse
    {
        $this->authorizeManage($club);

        $data = $request->validate([
            'full_name'     => ['required', 'string', 'min:3', 'max:120'],
            'document'      => ['nullable', 'string', 'max:40'],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'position'      => ['nullable', 'string', 'max:30'],
        ], [
            'full_name.required' => 'Indica el nombre del jugador.',
        ]);

        // `document` está cifrado — el anti-duplicado usa el blind index (document_hash).
        if (! empty($data['document']) && $club->players()->whereDocument($data['document'])->exists()) {
            return back()->withErrors(['document' => 'Ya hay un jugador con ese documento en la plantilla.']);
        }

        $member = ClubPlayer::create([
            'club_id'             => $club->id,
            'user_id'             => null,
            'full_name'           => $data['full_name'],
            'document'            => $data['document'] ?? null,
            'verification_status' => 'por_verificar',
            'jersey_number'       => $data['jersey_number'] ?? null,
            'position'            => $data['position'] ?? null,
            'status'              => 'active',
        ]);

        $this->membership->syncMemberAdded($member);

        return back()->with('status', "{$data['full_name']} agregado a la plantilla (por verificar).");
    }

    /** Quita un jugador de la plantilla permanente (no se puede al capitán). */
    public function removePlayer(Club $club, ClubPlayer $clubPlayer): RedirectResponse
    {
        $this->authorizeManage($club);
        abort_unless($clubPlayer->club_id === $club->id, 404);

        if ($clubPlayer->isCaptain()) {
            return back()->with('error', 'No se puede quitar al capitán. Asigná otro capitán primero.');
        }

        $this->membership->syncMemberRemoved($clubPlayer);
        $clubPlayer->delete();

        return back()->with('status', 'Jugador quitado de la plantilla.');
    }

    /** Asigna a otro miembro registrado como capitán del equipo. */
    public function setCaptain(Request $request, Club $club): RedirectResponse
    {
        $this->authorizeManage($club);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newCaptain = User::findOrFail($data['user_id']);

        // Debe ser un miembro registrado del equipo.
        $isMember = $club->players()->where('user_id', $newCaptain->id)->exists();
        if (! $isMember) {
            return back()->with('error', 'El nuevo capitán debe ser un jugador de la plantilla.');
        }

        $this->membership->changeCaptain($club, $newCaptain);

        return back()->with('status', "{$newCaptain->name} es ahora el capitán del equipo.");
    }

    // ─── Perfil público histórico ─────────────────────────────────────────────

    public function show(Club $club): View
    {
        $club->load(['teams.tournament']);

        $participations = $club->teams
            ->filter(fn ($t) => $t->tournament)
            ->sortByDesc(fn ($t) => $t->tournament->created_at)
            ->values();

        // Deuda #3: agregados históricos (PJ/G/E/P, goles, goleadores) cacheados
        // en club_stats en vez de recalcularse en cada lectura. Si el club
        // todavía no tiene cache (nunca se refrescó), se calcula una vez acá y
        // queda listo para las próximas lecturas.
        $clubStat = \App\Models\Torneos\ClubStat::where('club_id', $club->id)->first()
            ?? $this->clubStats->refreshForClub($club);

        $agg = [
            'played' => $clubStat->played, 'won' => $clubStat->won, 'drawn' => $clubStat->drawn,
            'lost' => $clubStat->lost, 'goals_for' => $clubStat->goals_for, 'goals_against' => $clubStat->goals_against,
        ];
        $topScorers = collect($clubStat->top_scorers ?? []);

        // Jugadores históricos = plantilla permanente.
        $players = $club->players()->with('user')->get();

        $canManage = $club->isCaptainedBy(auth()->user()) || auth()->user()->isAdmin();

        // ── FutGO Social (S1-C): historial de amistosos + métricas ────────────
        $friendlyService = app(\App\Services\Social\FriendlyMatchService::class);
        $friendlies      = $friendlyService->clubFriendlies($club);
        $friendlyMetrics = $friendlyService->clubMetrics($club);

        // ── FutGO Social (S3-A): historial de compatibilidad (head-to-head) ───
        $headToHead = $friendlyService->clubHeadToHead($club);

        // ── FutGO Social (S3-A): sugerencia de recategorización (solo capitán/admin)
        $levelSuggestion = $canManage
            ? app(\App\Services\Social\SuggestionService::class)->levelRecategorization($club)
            : null;

        // ── FutGO Social (S1-F): score de confiabilidad + oportunidades abiertas
        $reliabilityScore    = null;
        $reliabilityForOwner = null;
        $score = \App\Models\Social\ReliabilityScore::where('subject_type', 'club')
            ->where('subject_id', $club->id)
            ->first();
        if ($score) {
            // Score propio: siempre visible para capitán/admin.
            if ($canManage) {
                $reliabilityForOwner = $score;
            }
            // Score público: solo si supera el umbral.
            if ($score->score >= 80) {
                $reliabilityScore = $score;
            }
        }

        $openOpportunities = \App\Models\Social\Opportunity::query()
            ->where('club_id', $club->id)
            ->active()
            ->visible()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('torneos.clubes.show', compact(
            'club', 'participations', 'agg', 'players', 'topScorers', 'canManage',
            'friendlies', 'friendlyMetrics', 'headToHead', 'levelSuggestion',
            'reliabilityScore', 'reliabilityForOwner', 'openOpportunities',
        ));
    }

    /**
     * S3-A · El capitán ignora la sugerencia de recategorización de nivel:
     * se persiste para no volver a mostrarla. No cambia el nivel del club.
     */
    public function dismissLevelSuggestion(Club $club): RedirectResponse
    {
        $this->authorizeManage($club);

        $club->update(['level_suggestion_dismissed_at' => now()]);

        return back()->with('status', 'Listo, no te volveremos a sugerir cambiar el nivel.');
    }

    // ─────────────────────────────────────────────────────────────────

    /** Borra del disco de medios configurado un archivo a partir de su URL pública. */
    private function deleteFromMediaDisk(?string $url): void
    {
        if (! $url) {
            return;
        }
        $disk = config('filesystems.media_disk', 'public');
        $base = rtrim(Storage::disk($disk)->url(''), '/');
        if ($base && str_starts_with($url, $base . '/')) {
            Storage::disk($disk)->delete(substr($url, strlen($base) + 1));
        }
    }

    private function authorizeManage(Club $club): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $club->isCaptainedBy($user), 403);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'equipo';
        $slug = $base;
        $i = 1;
        while (Club::where('slug', $slug)->exists()) {
            $i++;
            $slug = $base . '-' . $i;
        }
        return $slug;
    }
}
