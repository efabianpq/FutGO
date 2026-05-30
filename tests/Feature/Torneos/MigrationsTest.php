<?php

namespace Tests\Feature\Torneos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabla_tournaments_existe(): void
    {
        $this->assertTrue(Schema::hasTable('tournaments'));
    }

    public function test_tabla_teams_existe(): void
    {
        $this->assertTrue(Schema::hasTable('teams'));
    }

    public function test_tabla_team_players_existe(): void
    {
        $this->assertTrue(Schema::hasTable('team_players'));
    }

    public function test_tabla_tournament_matches_existe(): void
    {
        $this->assertTrue(Schema::hasTable('tournament_matches'));
    }

    public function test_tabla_match_events_existe(): void
    {
        $this->assertTrue(Schema::hasTable('match_events'));
    }

    public function test_tabla_standings_existe(): void
    {
        $this->assertTrue(Schema::hasTable('standings'));
    }

    public function test_tabla_player_stats_existe(): void
    {
        $this->assertTrue(Schema::hasTable('player_stats'));
    }

    public function test_tournaments_tiene_columnas_criticas(): void
    {
        $this->assertTrue(Schema::hasColumns('tournaments', [
            'id', 'name', 'slug', 'sport', 'status', 'format',
            'groups_count', 'teams_per_group', 'classifies_per_group',
            'stats_config', 'created_by_user_id', 'starts_at', 'ends_at'
        ]));
    }

    public function test_tournament_matches_tiene_columnas_criticas(): void
    {
        $this->assertTrue(Schema::hasColumns('tournament_matches', [
            'id', 'phase_id', 'group_id', 'home_team_id', 'away_team_id',
            'winner_team_id', 'home_score', 'away_score', 'status',
            'scheduled_at', 'match_number'
        ]));
    }

    public function test_standings_tiene_columnas_criticas(): void
    {
        $this->assertTrue(Schema::hasColumns('standings', [
            'phase_id', 'group_id', 'team_id', 'played', 'won',
            'drawn', 'lost', 'goals_for', 'goals_against',
            'goal_difference', 'points', 'position'
        ]));
    }
}
