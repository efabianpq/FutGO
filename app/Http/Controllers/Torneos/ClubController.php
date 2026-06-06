<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\TournamentMatch;
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
    public function __construct(private ClubMembershipService $membership) {}

    /** Crea un equipo permanente; el creador queda como capitán y primer jugador. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'min:3', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.required' => 'Indicá el nombre del equipo.',
            'color.regex'   => 'El color debe estar en formato hexadecimal (#RRGGBB).',
        ]);

        // Un usuario no puede tener dos equipos con el mismo nombre.
        $dup = Club::where('captain_user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($data['name']))])
            ->exists();
        if ($dup) {
            return back()->withErrors(['name' => 'Ya tenés un equipo con ese nombre.'])->withInput();
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

        return redirect()
            ->route('torneos.clubes.manage', $club)
            ->with('status', 'Equipo creado. Ya sos su capitán.');
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

        if ($club->shield_url) {
            $path = ltrim(parse_url($club->shield_url, PHP_URL_PATH) ?? '', '/');
            if (str_starts_with($path, 'storage/')) {
                Storage::disk('public')->delete(substr($path, strlen('storage/')));
            }
        }

        $path = $request->file('shield')->store('clubs', 'public');
        $club->update(['shield_url' => Storage::disk('public')->url($path)]);

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
            return back()->withErrors(['user_id' => 'Elegí un jugador de la lista de sugerencias o ingresá un email válido.']);
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
            'full_name.required' => 'Indicá el nombre del jugador.',
        ]);

        if (! empty($data['document']) && $club->players()->where('document', $data['document'])->exists()) {
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

        $teamIds = $club->teams->pluck('id');

        $participations = $club->teams
            ->filter(fn ($t) => $t->tournament)
            ->sortByDesc(fn ($t) => $t->tournament->created_at)
            ->values();

        $matches = TournamentMatch::where('status', 'finished')
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->get();

        $agg = ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goals_for' => 0, 'goals_against' => 0];
        foreach ($matches as $m) {
            $isHome    = $teamIds->contains($m->home_team_id);
            $forScore  = (int) ($isHome ? $m->home_score : $m->away_score);
            $againstSc = (int) ($isHome ? $m->away_score : $m->home_score);
            $agg['played']++;
            $agg['goals_for']     += $forScore;
            $agg['goals_against'] += $againstSc;
            if ($forScore > $againstSc)      $agg['won']++;
            elseif ($forScore < $againstSc)  $agg['lost']++;
            else                             $agg['drawn']++;
        }

        // Jugadores históricos = plantilla permanente.
        $players = $club->players()->with('user')->get();

        $tpIds = TeamPlayer::whereIn('team_id', $teamIds)->pluck('id');
        $topScorers = PlayerStat::whereIn('team_player_id', $tpIds)
            ->with('teamPlayer.user')
            ->get()
            ->groupBy(fn ($s) => $s->teamPlayer?->user_id ?? 'guest-' . $s->team_player_id)
            ->map(fn ($rows) => [
                'name'  => $rows->first()->teamPlayer?->displayName() ?? '—',
                'goals' => (int) $rows->sum('goals'),
            ])
            ->filter(fn ($r) => $r['goals'] > 0)
            ->sortByDesc('goals')
            ->take(10)
            ->values();

        $canManage = $club->isCaptainedBy(auth()->user()) || auth()->user()->isAdmin();

        return view('torneos.clubes.show', compact(
            'club', 'participations', 'agg', 'players', 'topScorers', 'canManage'
        ));
    }

    // ─────────────────────────────────────────────────────────────────

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
