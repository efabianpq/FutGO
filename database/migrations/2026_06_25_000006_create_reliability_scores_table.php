<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · reliability_scores (cache).
 *
 * Score de confiabilidad 0-100 cacheado por sujeto (user|club), mismo patrón
 * que `fair_play_scores`: derivado y reconstruible desde `reliability_events`,
 * nunca en tiempo real. Guarda los contadores que lo componen y el flag
 * `is_paused`: la regla de negocio "2 no-shows en 30 días → disponibilidad
 * pausada (requiere reactivación manual)" se materializa acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reliability_scores', function (Blueprint $table) {
            $table->id();

            // Sujeto polimórfico: 'user' | 'club'.
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');

            $table->unsignedSmallInteger('score')->default(100);   // 0..100

            // Contadores que componen el score (reconstruibles).
            $table->unsignedInteger('no_shows')->default(0);
            $table->unsignedInteger('late_cancellations')->default(0);
            $table->unsignedInteger('fast_responses')->default(0);
            $table->unsignedInteger('positive_ratings')->default(0);
            $table->unsignedInteger('negative_ratings')->default(0);

            // Regla: 2 no-shows en 30 días → pausa automática de disponibilidad.
            $table->boolean('is_paused')->default(false);
            $table->dateTime('paused_at')->nullable();

            $table->dateTime('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
            $table->index(['subject_type', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reliability_scores');
    }
};
