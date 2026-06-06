<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de LOGROS configurables (Sesión F — gamificación).
 *
 * Agregar un nuevo logro = insertar una fila (no requiere cambios de esquema):
 *  - metric: qué acumulado se evalúa (matches_played, goals, assists, mvps,
 *            clean_sheets, wins, fair_play).
 *  - threshold: valor mínimo para otorgarlo.
 *  - min_matches: condición secundaria opcional (p.ej. fair_play exige PJ mínimos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();      // identificador estable
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('icon', 16)->nullable();     // emoji/insignia
            $table->string('metric', 40);               // qué acumulado evalúa
            $table->unsignedInteger('threshold')->default(1);
            $table->unsignedInteger('min_matches')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
