<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza el equipo PERMANENTE (club + club_players) con sus participaciones
 * por torneo (teams + team_players).
 *
 * Reglas de aprobación de jugadores en un torneo:
 *  - Al enrolar (primera inscripción): toda la plantilla queda APROBADA (active).
 *  - Jugador agregado luego, con el torneo aún en inscripción (open): queda active.
 *  - Jugador agregado luego, con el torneo EN CURSO (in_progress): queda 'pending'
 *    hasta que el ADMIN del torneo lo apruebe (debe conocer quién participa).
 *  - Torneos finished/cancelled: no se tocan (historial inmutable).
 */
class ClubMembershipService
{
    /** Enrola el equipo permanente en un torneo, copiando la plantilla como snapshot. */
    public function enroll(Club $club, Tournament $tournament): Team
    {
        return DB::transaction(function () use ($club, $tournament) {
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'club_id'         => $club->id,
                'captain_user_id' => $club->captain_user_id,
                'name'            => $club->name,
                'color'           => $club->color,
                'shield_url'      => $club->shield_url,
                'status'          => 'pending',
            ]);

            foreach ($club->players()->where('status', 'active')->get() as $cp) {
                TeamPlayer::create($this->teamPlayerAttrs($team, $cp, 'active'));
            }

            return $team;
        });
    }

    /** Propaga la alta de un miembro permanente a las participaciones no finalizadas. */
    public function syncMemberAdded(ClubPlayer $member): void
    {
        $teams = $this->activeTeams($member->club_id);

        foreach ($teams as $team) {
            if ($this->findTeamPlayer($team, $member)) {
                continue; // ya está en ese torneo
            }
            $status = $team->tournament->status === 'in_progress' ? 'pending' : 'active';
            TeamPlayer::create($this->teamPlayerAttrs($team, $member, $status));
        }
    }

    /** Propaga la baja de un miembro permanente a las participaciones no finalizadas. */
    public function syncMemberRemoved(ClubPlayer $member): void
    {
        foreach ($this->activeTeams($member->club_id) as $team) {
            $tp = $this->findTeamPlayer($team, $member);
            if (! $tp) {
                continue;
            }
            // En inscripción se elimina; en curso se marca inactivo (preserva stats).
            if ($team->tournament->status === 'in_progress') {
                $tp->update(['status' => 'inactive']);
            } else {
                $tp->delete();
            }
        }
    }

    /** Cambia el capitán permanente y lo propaga a participaciones no finalizadas. */
    public function changeCaptain(Club $club, User $newCaptain): void
    {
        DB::transaction(function () use ($club, $newCaptain) {
            // Debe ser miembro de la plantilla permanente.
            $newCp = $club->players()->where('user_id', $newCaptain->id)->first();
            if (! $newCp) {
                $newCp = ClubPlayer::create([
                    'club_id'             => $club->id,
                    'user_id'             => $newCaptain->id,
                    'verification_status' => 'registrado',
                    'status'              => 'active',
                    'is_captain'          => false,
                ]);
                $this->syncMemberAdded($newCp);
            }

            $club->players()->update(['is_captain' => false]);
            $newCp->update(['is_captain' => true, 'status' => 'active']);
            $club->update(['captain_user_id' => $newCaptain->id]);

            // Propagar a las participaciones no finalizadas.
            foreach ($this->activeTeams($club->id) as $team) {
                $team->update(['captain_user_id' => $newCaptain->id]);
                $team->players()->update(['is_captain' => false]);
                $tp = $team->players()->where('user_id', $newCaptain->id)->first();
                if ($tp) {
                    $tp->update(['is_captain' => true, 'status' => $tp->status === 'inactive' ? 'inactive' : 'active']);
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────

    /** Participaciones del club en torneos no finalizados. */
    private function activeTeams(int $clubId)
    {
        return Team::where('club_id', $clubId)
            ->whereHas('tournament', fn ($q) => $q->whereIn('status', ['open', 'in_progress']))
            ->with('tournament')
            ->get();
    }

    /** Busca el team_player correspondiente a un miembro permanente. */
    private function findTeamPlayer(Team $team, ClubPlayer $member): ?TeamPlayer
    {
        $q = $team->players();
        if ($member->user_id) {
            return $q->where('user_id', $member->user_id)->first();
        }
        if (! empty($member->document)) {
            // `document` está cifrado — se busca por el blind index (document_hash).
            return $q->whereDocument($member->document)->first();
        }
        return $q->whereNull('user_id')->where('full_name', $member->full_name)->first();
    }

    /** Atributos de team_player derivados de un miembro permanente. */
    private function teamPlayerAttrs(Team $team, ClubPlayer $cp, string $status): array
    {
        return [
            'team_id'             => $team->id,
            'user_id'             => $cp->user_id,
            'is_captain'          => (bool) $cp->is_captain,
            'full_name'           => $cp->full_name,
            'document'            => $cp->document,
            'verification_status' => $cp->verification_status,
            'jersey_number'       => $cp->jersey_number,
            'position'            => $cp->position,
            'status'              => $status,
        ];
    }
}
