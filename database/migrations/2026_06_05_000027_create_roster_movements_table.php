<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de movimientos de plantilla DURANTE el torneo: bajas y cambios de
 * equipo. Es un registro de auditoría; NO borra estadísticas ya generadas.
 *
 * type:
 *  - 'baja'   = el jugador deja de participar en el torneo (team_player → inactive).
 *  - 'cambio' = transferencia de un equipo a otro dentro del mismo torneo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('team_player_id')->nullable()->constrained('team_players')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('player_name', 120)->nullable();   // snapshot del nombre
            $table->enum('type', ['baja', 'cambio']);
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_movements');
    }
};
