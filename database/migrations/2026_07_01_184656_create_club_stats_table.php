<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stats agregadas de club cacheadas (deuda #3). Antes se recalculaban en cada
 * lectura del perfil del club con un ->get() sin límite de TODOS los partidos
 * finalizados históricos de sus equipos — crece linealmente con el historial.
 * Mismo patrón que fair_play_scores/futgo_rankings/reliability_scores: cache
 * reconstruible con calculated_at, poblada por ClubStatsService.
 *
 * A diferencia de esas tablas (subject_type/subject_id, porque aplican a
 * jugador Y equipo), club_stats es exclusiva de clubs: FK directa club_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->unique()->constrained('clubs')->cascadeOnDelete();
            $table->unsignedInteger('played')->default(0);
            $table->unsignedInteger('won')->default(0);
            $table->unsignedInteger('drawn')->default(0);
            $table->unsignedInteger('lost')->default(0);
            $table->unsignedInteger('goals_for')->default(0);
            $table->unsignedInteger('goals_against')->default(0);
            // Top 10 goleadores históricos del club: [{name, goals}, ...] denormalizado.
            $table->json('top_scorers')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_stats');
    }
};
