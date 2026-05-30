<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los partidos de eliminatoria se generan como placeholders (sin equipos)
 * hasta que los clasificados de la fase previa avanzan. Por eso home_team_id
 * y away_team_id deben poder ser NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->unsignedBigInteger('home_team_id')->nullable()->change();
            $table->unsignedBigInteger('away_team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->unsignedBigInteger('home_team_id')->nullable(false)->change();
            $table->unsignedBigInteger('away_team_id')->nullable(false)->change();
        });
    }
};
