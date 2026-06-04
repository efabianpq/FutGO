<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acumulado histórico del jugador — "hoja de vida deportiva" (Sesión B).
 *
 * DECISIÓN: tabla agregada persistente (1 fila por usuario) en lugar de cache.
 * Razones: durable (sobrevive a flush de cache), consultable/ordenable (habilita
 * rankings históricos a futuro) y se consolida en el mismo pipeline de recálculo
 * de estadísticas (escritura) en vez de recomputar en cada request (lectura O(1)).
 *
 * Es un DERIVADO de player_stats (across torneos): siempre puede reconstruirse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_career_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('goals')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('yellow_cards')->default(0);
            $table->unsignedInteger('red_cards')->default(0);
            $table->unsignedInteger('minutes_played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('clean_sheets')->default(0);
            $table->unsignedInteger('mvps')->default(0);

            $table->unsignedInteger('tournaments_count')->default(0);
            $table->unsignedInteger('teams_count')->default(0);

            $table->timestamp('last_consolidated_at')->nullable();
            $table->timestamps();

            $table->index('goals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_career_stats');
    }
};
