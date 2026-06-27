<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · reliability_events.
 *
 * Historial de eventos que alimentan el SCORE DE CONFIABILIDAD (distinto del
 * fair play: mide asistencia/puntualidad, no disciplina). Polimórfico sobre el
 * sujeto (user|club) vía morph map. Referencia opcional a la oportunidad o
 * partido que originó el evento. El score 0-100 se reconstruye en cache
 * (`reliability_scores`), clonando el patrón de FairPlayService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reliability_events', function (Blueprint $table) {
            $table->id();

            // Sujeto polimórfico: 'user' (users.id) | 'club' (clubs.id).
            $table->morphs('subject');   // subject_type + subject_id (+ índice)

            // no_show | cancelacion_tardia | respuesta_rapida |
            // calificacion_positiva | calificacion_negativa
            $table->string('type', 30);

            // Origen del evento (uno, ambos o ninguno).
            $table->foreignId('opportunity_id')->nullable()
                  ->constrained('opportunities')->nullOnDelete();
            $table->foreignId('friendly_match_id')->nullable()
                  ->constrained('friendly_matches')->nullOnDelete();

            // Contexto adicional (quién calificó, severidad, etc.).
            $table->json('meta')->nullable();

            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reliability_events');
    }
};
