<?php

namespace App\Services\Torneos;

use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;

/**
 * Orquestador de reputación (Sesión F). Coordina el recálculo de acumulados,
 * fair play, logros y ranking. NO se ejecuta por request: solo al finalizar un
 * torneo o vía el comando torneos:rebuild-reputation (cron).
 */
class ReputationService
{
    public function __construct(
        private PlayerCareerStatsService $career,
        private FairPlayService $fairPlay,
        private AchievementService $achievements,
        private RankingService $ranking,
    ) {}

    /** Consolidación al finalizar un torneo: acumulados → fair play → logros → ranking. */
    public function consolidateTournament(Tournament $tournament): void
    {
        $this->career->refreshForTournament($tournament);
        $this->fairPlay->refreshForTournament($tournament);
        $this->achievements->evaluateForTournament($tournament);
        $this->ranking->rebuild();
    }

    /** Reconstrucción total (comando periódico). */
    public function rebuildAll(): void
    {
        $userIds = TeamPlayer::whereNotNull('user_id')->distinct()->pluck('user_id');
        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->career->refreshForUser($user);
        }

        $this->fairPlay->rebuild();
        $this->achievements->rebuild();
        $this->ranking->rebuild();
    }
}
