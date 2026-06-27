<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Achievement;
use App\Models\Torneos\FairPlayScore;
use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\UserAchievement;
use App\Models\User;

/**
 * Otorgamiento automático de logros (Sesión F).
 *
 * Evalúa el catálogo ACTIVO contra los acumulados del jugador (player_career_stats)
 * y su fair play. Otorga el logro si cumple la condición y no lo tenía (idempotente
 * por firstOrCreate + unique user+achievement → nunca se otorga dos veces).
 */
class AchievementService
{
    public function __construct(
        private PlayerCareerStatsService $career,
        private FairPlayService $fairPlay,
        private \App\Services\Social\FeedService $feed,
    ) {}

    /**
     * Evalúa y otorga logros a un jugador. Devuelve los logros recién otorgados.
     *
     * @return array<int,Achievement>
     */
    public function evaluateForUser(User $user): array
    {
        // Se leen los valores más recientes de BD (no la relación, que puede estar cacheada).
        $career = PlayerCareerStat::firstWhere('user_id', $user->id)
            ?? $this->career->refreshForUser($user);

        $fairPlay = FairPlayScore::where('subject_type', 'player')->where('subject_id', $user->id)->value('score')
            ?? $this->fairPlay->refreshForUser($user)->score;

        $granted = [];

        foreach (Achievement::active()->get() as $achievement) {
            $value = $this->metricValue($achievement->metric, $career, (int) $fairPlay);

            $meets = $value >= $achievement->threshold
                && ($achievement->min_matches === null || $career->matches_played >= $achievement->min_matches);

            if (! $meets) {
                continue;
            }

            $assignment = UserAchievement::firstOrCreate(
                ['user_id' => $user->id, 'achievement_id' => $achievement->id],
                ['awarded_at' => now()],
            );

            if ($assignment->wasRecentlyCreated) {
                $granted[] = $achievement;

                // Feed (no bloqueante): los seguidores del jugador ven el logro.
                $this->feed->record(
                    \App\Models\Social\FeedEvent::TYPE_LOGRO_DESBLOQUEADO,
                    $user,
                    $achievement,
                    ['payload' => [
                        'achievement_id'   => $achievement->id,
                        'achievement_name' => $achievement->name ?? null,
                        'player'           => $user->name,
                    ]]
                );
            }
        }

        return $granted;
    }

    /** Evalúa logros para todos los jugadores registrados de un torneo. */
    public function evaluateForTournament(Tournament $tournament): void
    {
        $userIds = TeamPlayer::whereIn('team_id', $tournament->teams()->pluck('id'))
            ->whereNotNull('user_id')->pluck('user_id')->unique();

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->evaluateForUser($user);
        }
    }

    /** Re-evalúa logros de todos los jugadores con inscripción. */
    public function rebuild(): void
    {
        $userIds = TeamPlayer::whereNotNull('user_id')->distinct()->pluck('user_id');
        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->evaluateForUser($user);
        }
    }

    private function metricValue(string $metric, PlayerCareerStat $career, int $fairPlay): int
    {
        return match ($metric) {
            'matches_played' => (int) $career->matches_played,
            'goals'          => (int) $career->goals,
            'assists'        => (int) $career->assists,
            'mvps'           => (int) $career->mvps,
            'clean_sheets'   => (int) $career->clean_sheets,
            'wins'           => (int) $career->wins,
            'fair_play'      => $fairPlay,
            default          => 0,
        };
    }
}
