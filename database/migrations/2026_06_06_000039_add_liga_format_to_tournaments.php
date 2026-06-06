<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H6: nuevo formato de torneo 'liga'.
 *
 * A diferencia de los demás, el formato liga NO genera el fixture automáticamente:
 * el admin activa el torneo, agrega partidos a mano o auto-genera todos contra
 * todos, y luego puede generar la eliminatoria desde la tabla de posiciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('format', ['groups_and_knockout', 'knockout_only', 'round_robin', 'liga'])
                  ->default('groups_and_knockout')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('format', ['groups_and_knockout', 'knockout_only', 'round_robin'])
                  ->default('groups_and_knockout')
                  ->change();
        });
    }
};
