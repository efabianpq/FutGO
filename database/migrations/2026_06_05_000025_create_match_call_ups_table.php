<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convocatoria PREVIA al partido (planeación deportiva) + confirmación de asistencia.
 *
 * Diferencia clave con match_lineups:
 *  - match_call_ups = PLANEACIÓN antes del partido: el capitán convoca y cada
 *    jugador confirma/declina su asistencia (status: convocado/confirmado/declinado).
 *  - match_lineups  = ALINEACIÓN real CONFIRMADA del partido (titular, minutos),
 *    cargada por el admin al ingresar el resultado; es la fuente de las stats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_call_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->foreignId('team_player_id')->constrained('team_players')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->enum('status', ['convocado', 'confirmado', 'declinado'])->default('convocado');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'team_player_id']);
            $table->index(['match_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_call_ups');
    }
};
