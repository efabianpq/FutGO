<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría del SORTEO de desempate ('drawing') en la tabla de posiciones (Sesión F).
 *
 * Solo se registran las posiciones resueltas POR SORTEO (empate absoluto que ningún
 * otro criterio del tiebreaker_order resolvió). El sorteo es DETERMINISTA a partir de
 * un seed estable (tournament:phase:group) → reproducible entre recálculos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained('tournament_phases')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('tournament_groups')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedBigInteger('seed');           // semilla determinista del grupo
            $table->unsignedInteger('draw_position');     // posición asignada por sorteo
            $table->timestamps();

            $table->index(['phase_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_draws');
    }
};
