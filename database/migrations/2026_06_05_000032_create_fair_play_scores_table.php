<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fair Play Score cacheado (Sesión F) para jugadores y equipos (clubs).
 *
 * subject_type: 'player' (subject_id = users.id) | 'team' (subject_id = clubs.id).
 * Fórmula jugador: max(0, 100 − 3·amarillas − 10·rojas − 5·inasistencias).
 * Fórmula equipo: promedio del fair play de sus jugadores registrados.
 * Alimenta el ranking y la reputación. Derivado: reconstruible siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fair_play_scores', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', ['player', 'team']);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedSmallInteger('score')->default(100);   // 0..100
            $table->unsignedInteger('yellow_cards')->default(0);
            $table->unsignedInteger('red_cards')->default(0);
            $table->unsignedInteger('absences')->default(0);
            $table->unsignedInteger('matches')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
            $table->index(['subject_type', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fair_play_scores');
    }
};
