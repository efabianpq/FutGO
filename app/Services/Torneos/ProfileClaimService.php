<?php

namespace App\Services\Torneos;

use App\Exceptions\Torneos\ProfileClaimException;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\ProfileClaim;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Notifications\ProfileClaimResolvedNotification;
use App\Notifications\ProfileClaimSubmittedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta el ciclo de "reclamo de perfil" (Limitación #2): vincular la cuenta de
 * un usuario recién registrado a un registro `club_players` 'por_verificar' que un
 * capitán creó sin cuenta, heredando su historial deportivo.
 *
 * Garantías de calidad:
 *  - El reclamo NUNCA vincula sin aprobación humana (capitán → o admin si escalado).
 *  - Un mismo registro no admite dos reclamos vivos a la vez (doble reclamo bloqueado).
 *  - Un documento no queda vinculado a dos user_id distintos: al aprobar, el registro
 *    pasa a 'registrado' y deja de ser candidato; el guard de aprobación lo refuerza.
 */
class ProfileClaimService
{
    public function __construct(
        private PlayerCareerStatsService $careerStats,
        private FairPlayService $fairPlay,
    ) {
    }

    /**
     * Normaliza un documento para comparar: minúsculas y sin separadores
     * (puntos, guiones, espacios). Dos personas pueden escribir "12.345.678"
     * y "12345678" — son el mismo documento.
     */
    public static function normalizeDocument(?string $doc): ?string
    {
        if ($doc === null) {
            return null;
        }
        $clean = preg_replace('/[^a-z0-9]/i', '', $doc);

        return $clean === '' ? null : mb_strtolower($clean);
    }

    /**
     * Registros 'por_verificar' (sin cuenta) cuyo documento coincide con el del
     * usuario y que todavía no tienen un reclamo vivo ni pertenecen a un club
     * donde el usuario ya es miembro. Son los candidatos a reclamar.
     */
    public function findCandidatesFor(User $user): Collection
    {
        $doc = static::normalizeDocument($user->document);
        if ($doc === null) {
            return collect();
        }

        // Comparación en PHP tras normalizar (documentos pueden traer puntos/guiones).
        $candidates = ClubPlayer::query()
            ->where('verification_status', 'por_verificar')
            ->whereNull('user_id')
            ->whereNotNull('document')
            ->with('club')
            ->get()
            ->filter(fn (ClubPlayer $cp) => static::normalizeDocument($cp->document) === $doc);

        if ($candidates->isEmpty()) {
            return collect();
        }

        // Excluir registros con un reclamo vivo (ya tomados temporalmente).
        $claimedIds = ProfileClaim::open()
            ->whereIn('club_player_id', $candidates->pluck('id'))
            ->pluck('club_player_id')
            ->all();

        // Excluir clubs donde el usuario ya es miembro (evita unique(club_id,user_id)).
        $memberClubIds = ClubPlayer::where('user_id', $user->id)
            ->whereIn('club_id', $candidates->pluck('club_id')->unique())
            ->pluck('club_id')
            ->all();

        return $candidates
            ->reject(fn (ClubPlayer $cp) => in_array($cp->id, $claimedIds, true))
            ->reject(fn (ClubPlayer $cp) => in_array($cp->club_id, $memberClubIds, true))
            ->values();
    }

    /** ¿Cuántos registros podría reclamar este usuario? (badge / aviso). */
    public function countCandidatesFor(User $user): int
    {
        return $this->findCandidatesFor($user)->count();
    }

    /**
     * Inicia un reclamo. Queda PENDIENTE de aprobación del capitán; si el club no
     * tiene capitán (o el capitán ya no existe), nace ESCALADO a un admin.
     */
    public function claim(User $user, ClubPlayer $clubPlayer): ProfileClaim
    {
        if (! $clubPlayer->isPorVerificar() || $clubPlayer->user_id !== null) {
            throw ProfileClaimException::make('Ese registro ya no está disponible para reclamar.');
        }

        $userDoc = static::normalizeDocument($user->document);
        if ($userDoc === null || $userDoc !== static::normalizeDocument($clubPlayer->document)) {
            throw ProfileClaimException::make('Tu documento no coincide con el de ese registro.');
        }

        // Doble reclamo bloqueado: un registro no admite dos reclamos vivos.
        if ($clubPlayer->profileClaims()->open()->exists()) {
            throw ProfileClaimException::make('Ese registro ya tiene un reclamo en curso.');
        }

        // No reclamar en un club donde ya eres miembro con tu cuenta.
        $alreadyMember = ClubPlayer::where('club_id', $clubPlayer->club_id)
            ->where('user_id', $user->id)
            ->exists();
        if ($alreadyMember) {
            throw ProfileClaimException::make('Ya eres miembro de ese equipo con tu cuenta.');
        }

        $club = $clubPlayer->club ?? Club::find($clubPlayer->club_id);
        $escalate = $this->shouldEscalate($club);

        $claim = ProfileClaim::create([
            'user_id'        => $user->id,
            'club_player_id' => $clubPlayer->id,
            'club_id'        => $clubPlayer->club_id,
            'document'       => $clubPlayer->document,
            'status'         => $escalate ? 'escalated' : 'pending',
            'escalated_at'   => $escalate ? now() : null,
        ]);

        $this->notifySubmitted($claim->fresh(['user', 'club.captain', 'clubPlayer']));

        return $claim;
    }

    /**
     * Aprueba el reclamo: vincula la cuenta al registro permanente, HEREDA el
     * historial (backfill de team_players) y refresca los derivados (career +
     * fair play). Idempotente frente a reclamos ya resueltos.
     */
    public function approve(ProfileClaim $claim, User $approver): ProfileClaim
    {
        $this->authorizeResolver($claim, $approver);

        if (! $claim->isOpen()) {
            throw ProfileClaimException::make('Ese reclamo ya fue resuelto.');
        }

        $clubPlayer = $claim->clubPlayer;
        if (! $clubPlayer || $clubPlayer->user_id !== null || ! $clubPlayer->isPorVerificar()) {
            throw ProfileClaimException::make('El registro ya no está disponible para vincular.');
        }

        // Guard: un documento no puede quedar vinculado a dos user_id en el mismo club.
        $conflict = ClubPlayer::where('club_id', $clubPlayer->club_id)
            ->where('user_id', $claim->user_id)
            ->exists();
        if ($conflict) {
            throw ProfileClaimException::make('El usuario ya está vinculado a un registro de este club.');
        }

        DB::transaction(function () use ($claim, $clubPlayer, $approver) {
            $userId = $claim->user_id;

            // 1. Vincular el registro permanente → ahora 'registrado'.
            $clubPlayer->update([
                'user_id'             => $userId,
                'verification_status' => 'registrado',
            ]);

            // 2. Heredar historial: team_players 'por_verificar' del club que
            //    coinciden por documento pasan a apuntar a la cuenta.
            $this->transferHistory($clubPlayer, $userId);

            // 3. Cerrar el reclamo.
            $claim->update([
                'status'              => 'approved',
                'resolved_by_user_id' => $approver->id,
                'resolved_at'         => now(),
            ]);

            // 4. Rechazar cualquier otro reclamo vivo del mismo registro.
            ProfileClaim::where('club_player_id', $clubPlayer->id)
                ->where('id', '!=', $claim->id)
                ->open()
                ->update([
                    'status'          => 'rejected',
                    'resolved_at'     => now(),
                    'resolution_note' => 'El registro fue vinculado mediante otro reclamo.',
                ]);
        });

        // 5. Refrescar derivados del usuario (lee player_stats ya vinculados).
        //    El jugador hereda su acumulado, entra al fair play del equipo (#12) y
        //    pasa a recibir notificaciones de partido (#13, vía team_players.user_id).
        $user = $claim->user;
        $this->careerStats->refreshForUser($user);
        $this->fairPlay->refreshForUser($user);

        $this->notifyResolved($claim->fresh(['user']), approved: true);

        return $claim->fresh();
    }

    /** Rechaza el reclamo: el registro queda sin cambios. */
    public function reject(ProfileClaim $claim, User $resolver, ?string $note = null): ProfileClaim
    {
        $this->authorizeResolver($claim, $resolver);

        if (! $claim->isOpen()) {
            throw ProfileClaimException::make('Ese reclamo ya fue resuelto.');
        }

        $claim->update([
            'status'              => 'rejected',
            'resolved_by_user_id' => $resolver->id,
            'resolved_at'         => now(),
            'resolution_note'     => $note,
        ]);

        $this->notifyResolved($claim->fresh(['user']), approved: false);

        return $claim;
    }

    /**
     * Escala manualmente un reclamo pendiente a los admins (el capitán no responde).
     * Para clubs sin capitán el reclamo ya nace escalado.
     */
    public function escalate(ProfileClaim $claim): ProfileClaim
    {
        if ($claim->status !== 'pending') {
            throw ProfileClaimException::make('Solo un reclamo pendiente puede escalarse.');
        }

        $claim->update([
            'status'       => 'escalated',
            'escalated_at' => now(),
        ]);

        $this->notifyAdminsEscalated($claim->fresh(['user', 'club', 'clubPlayer']));

        return $claim;
    }

    /** Reclamos PENDIENTES de los clubs que capitanea el usuario (bandeja de aprobación). */
    public function pendingForCaptain(User $user): Collection
    {
        $clubIds = Club::where('captain_user_id', $user->id)->pluck('id');
        if ($clubIds->isEmpty()) {
            return collect();
        }

        return ProfileClaim::pending()
            ->whereIn('club_id', $clubIds)
            ->with(['user', 'clubPlayer', 'club'])
            ->latest()
            ->get();
    }

    /** Reclamos ESCALADOS a la plataforma (bandeja de admin). */
    public function escalatedForAdmin(): Collection
    {
        return ProfileClaim::escalated()
            ->with(['user', 'clubPlayer', 'club'])
            ->latest()
            ->get();
    }

    /** Torneos en los que aparece el registro (por documento) — para mostrar el contexto. */
    public function tournamentsForClubPlayer(ClubPlayer $clubPlayer): Collection
    {
        $docNorm = static::normalizeDocument($clubPlayer->document);
        if ($docNorm === null) {
            return collect();
        }

        $teamIds = Team::where('club_id', $clubPlayer->club_id)->pluck('id');
        if ($teamIds->isEmpty()) {
            return collect();
        }

        $matchingTeamIds = TeamPlayer::whereIn('team_id', $teamIds)
            ->whereNotNull('document')
            ->get(['id', 'team_id', 'document'])
            ->filter(fn ($tp) => static::normalizeDocument($tp->document) === $docNorm)
            ->pluck('team_id')
            ->unique();

        if ($matchingTeamIds->isEmpty()) {
            return collect();
        }

        $tournamentIds = Team::whereIn('id', $matchingTeamIds)->pluck('tournament_id')->unique();

        return Tournament::whereIn('id', $tournamentIds)->get(['id', 'name', 'slug']);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Backfill del historial: los team_players 'por_verificar' de las
     * participaciones del club que coinciden por documento se vinculan a la cuenta.
     * Salta cualquiera que rompería unique(team_id, user_id) (el equipo ya tiene
     * a ese user en otra fila).
     */
    private function transferHistory(ClubPlayer $clubPlayer, int $userId): void
    {
        $docNorm = static::normalizeDocument($clubPlayer->document);
        if ($docNorm === null) {
            return;
        }

        $teamIds = Team::where('club_id', $clubPlayer->club_id)->pluck('id');
        if ($teamIds->isEmpty()) {
            return;
        }

        $rows = TeamPlayer::whereIn('team_id', $teamIds)
            ->where('verification_status', 'por_verificar')
            ->whereNull('user_id')
            ->whereNotNull('document')
            ->get()
            ->filter(fn (TeamPlayer $tp) => static::normalizeDocument($tp->document) === $docNorm);

        foreach ($rows as $tp) {
            $dup = TeamPlayer::where('team_id', $tp->team_id)
                ->where('user_id', $userId)
                ->exists();
            if ($dup) {
                continue; // el equipo ya tiene a ese usuario (no duplicar).
            }
            $tp->update([
                'user_id'             => $userId,
                'verification_status' => 'registrado',
            ]);
        }
    }

    /** ¿El reclamo debe nacer escalado? (club sin capitán o capitán inexistente). */
    private function shouldEscalate(?Club $club): bool
    {
        if (! $club || ! $club->captain_user_id) {
            return true;
        }

        return ! User::whereKey($club->captain_user_id)->exists();
    }

    /** Autoriza a resolver: capitán del club (pendiente) o admin (siempre). */
    private function authorizeResolver(ProfileClaim $claim, User $resolver): void
    {
        if ($resolver->isAdmin()) {
            return;
        }

        if ($claim->status === 'pending'
            && $claim->club
            && $claim->club->captain_user_id === $resolver->id) {
            return;
        }

        throw ProfileClaimException::make('No tienes permiso para resolver este reclamo.');
    }

    /** Notifica a quien debe aprobar (capitán o, si escalado, los admins). */
    private function notifySubmitted(ProfileClaim $claim): void
    {
        try {
            if ($claim->isEscalated()) {
                $this->notifyAdminsEscalated($claim);

                return;
            }

            $captain = $claim->club?->captain;
            if ($captain) {
                $captain->notify(new ProfileClaimSubmittedNotification($claim));
            }
        } catch (\Throwable $e) {
            Log::warning('ProfileClaim submitted notification failed', [
                'claim_id' => $claim->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function notifyAdminsEscalated(ProfileClaim $claim): void
    {
        try {
            foreach (User::where('role', 'admin')->get() as $admin) {
                $admin->notify(new ProfileClaimSubmittedNotification($claim));
            }
        } catch (\Throwable $e) {
            Log::warning('ProfileClaim escalation notification failed', [
                'claim_id' => $claim->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function notifyResolved(ProfileClaim $claim, bool $approved): void
    {
        try {
            $claim->user?->notify(new ProfileClaimResolvedNotification($claim, $approved));
        } catch (\Throwable $e) {
            Log::warning('ProfileClaim resolved notification failed', [
                'claim_id' => $claim->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
