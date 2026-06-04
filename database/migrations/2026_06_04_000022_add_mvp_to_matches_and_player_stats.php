<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP del partido (Sesión B).
 * - tournament_matches.mvp_team_player_id: jugador "figura del partido" (opcional).
 * - player_stats.mvps: cantidad de MVP del jugador en el torneo (agregable al histórico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->foreignId('mvp_team_player_id')->nullable()->after('winner_team_id')
                  ->constrained('team_players')->nullOnDelete();
        });

        Schema::table('player_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('mvps')->default(0)->after('clean_sheets');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropForeign(['mvp_team_player_id']);
            $table->dropColumn('mvp_team_player_id');
        });

        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropColumn('mvps');
        });
    }
};
