<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · FriendlyMatch (amistoso confirmado).
 *
 * Entidad PROPIA, sin `tournament_id`: se crea al aceptar una Opportunity de
 * tipo BUSCAR_RIVAL. `location` es texto libre (venues llega en Fase 3).
 *
 * DOBLE CONFIRMACIÓN DE RESULTADO (requerimiento de negocio):
 * cada club reporta su marcador (home_reported_* / away_reported_*). Si ambos
 * coinciden → `jugado` + `result_agreement=acordado` y se fijan los final_*.
 * Si difieren → `en_disputa`, sin afectar la reputación de nadie hasta que un
 * admin/los capitanes resuelvan. Evita el clásico "cada uno dice que ganó".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendly_matches', function (Blueprint $table) {
            $table->id();

            // Oportunidad que lo originó (nullable: puede crearse manualmente).
            $table->foreignId('opportunity_id')->nullable()
                  ->constrained('opportunities')->nullOnDelete();

            // Los dos clubs participantes (entidad permanente, no `teams`).
            $table->foreignId('home_club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('away_club_id')->constrained('clubs')->cascadeOnDelete();

            $table->dateTime('scheduled_at')->nullable();
            $table->string('location', 255)->nullable();   // texto libre por ahora

            // confirmado | en_disputa | jugado | cancelado
            $table->string('status', 20)->default('confirmado');

            // Doble confirmación — marcador propuesto por el LOCAL.
            $table->unsignedTinyInteger('home_reported_home_score')->nullable();
            $table->unsignedTinyInteger('home_reported_away_score')->nullable();

            // Doble confirmación — marcador propuesto por el VISITANTE.
            $table->unsignedTinyInteger('away_reported_home_score')->nullable();
            $table->unsignedTinyInteger('away_reported_away_score')->nullable();

            // null | pendiente | acordado | en_disputa
            $table->string('result_agreement', 20)->nullable();

            // Marcador final acordado (solo cuando ambos coinciden).
            $table->unsignedTinyInteger('final_home_score')->nullable();
            $table->unsignedTinyInteger('final_away_score')->nullable();

            // Cancelación (alimenta reliability_events si es tardía).
            $table->foreignId('cancelled_by_club_id')->nullable()
                  ->constrained('clubs')->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['home_club_id', 'away_club_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendly_matches');
    }
};
