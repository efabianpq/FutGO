<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W.O. (walkover): un equipo no se presentó. El partido tiene ganador y un
 * marcador convencional, pero no se cargan goles a jugadores ni convocatoria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->boolean('is_walkover')->default(false)->after('winner_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('is_walkover');
        });
    }
};
