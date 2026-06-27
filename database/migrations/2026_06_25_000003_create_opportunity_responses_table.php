<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · OpportunityResponse.
 *
 * Respuesta de otro actor (usuario o club) a una Opportunity publicada.
 * Una oportunidad tiene múltiples respuestas pero SOLO UNA aceptada — la regla
 * se garantiza en el servicio de aceptación (transacción), no con índice
 * parcial (no portable a SQLite). `message` es texto corto estructurado: el
 * único canal del MVP antes de la mensajería libre (Fase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();

            // Quién responde: user y/o club según el tipo de oportunidad.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();

            $table->string('message', 500)->nullable();

            // pendiente | aceptada | rechazada | contrapropuesta
            $table->string('status', 20)->default('pendiente');

            $table->timestamps();

            $table->index(['opportunity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_responses');
    }
};
