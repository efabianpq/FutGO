<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía los estados de un jugador de equipo:
 *   pending  → solicitud de ingreso esperando aprobación del capitán
 *   active   → jugador confirmado (default; el capitán agrega activos directamente)
 *   inactive → dado de baja (p. ej. tras tarjeta roja)
 *   rejected → solicitud rechazada por el capitán (se conserva para trazabilidad)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'inactive', 'rejected'])
                  ->default('active')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])
                  ->default('active')
                  ->change();
        });
    }
};
