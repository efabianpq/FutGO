<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de la Planilla Oficial de Juego (acta del partido):
 * cuerpo arbitral, marcador por periodos y datos por equipo (cuerpo técnico,
 * faltas acumulativas, tiempos muertos, firma del capitán).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            // Cuerpo arbitral y mesa.
            $table->string('referee', 120)->nullable()->after('observations');
            $table->string('second_referee', 120)->nullable()->after('referee');
            $table->string('third_referee', 120)->nullable()->after('second_referee');
            $table->string('timekeeper', 120)->nullable()->after('third_referee');   // Cronometrador
            $table->string('coordinator', 120)->nullable()->after('timekeeper');

            // Marcador por periodos (el resultado final sigue en home_score/away_score).
            $table->unsignedTinyInteger('home_score_ht')->nullable()->after('coordinator'); // 1er tiempo
            $table->unsignedTinyInteger('away_score_ht')->nullable()->after('home_score_ht');
            $table->unsignedTinyInteger('home_score_et')->nullable()->after('away_score_ht'); // prórroga
            $table->unsignedTinyInteger('away_score_et')->nullable()->after('home_score_et');
            $table->unsignedTinyInteger('home_penalties')->nullable()->after('away_score_et');
            $table->unsignedTinyInteger('away_penalties')->nullable()->after('home_penalties');

            // Datos por equipo del acta (cuerpo técnico, faltas, tiempos muertos, firmas).
            $table->json('match_sheet')->nullable()->after('away_penalties');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn([
                'referee', 'second_referee', 'third_referee', 'timekeeper', 'coordinator',
                'home_score_ht', 'away_score_ht', 'home_score_et', 'away_score_et',
                'home_penalties', 'away_penalties', 'match_sheet',
            ]);
        });
    }
};
