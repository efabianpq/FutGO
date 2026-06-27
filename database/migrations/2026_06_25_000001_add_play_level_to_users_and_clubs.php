<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · Niveles de juego (requisito de matching).
 *
 * El nivel declarado (recreativo/intermedio/competitivo/elite_amateur) es
 * OBLIGATORIO para participar en oportunidades: sin nivel, el matching de
 * "Buscar rival/jugador" no puede filtrar y emparejaría categorías dispares.
 *
 * Es un filtro real, no decorativo: se almacena en `users` (jugador) y en
 * `clubs` (equipo). Nullable porque declararlo es una acción explícita del
 * actor; los registros previos quedan sin nivel hasta que lo declaren.
 *
 * EXCEPCIÓN sancionada a "no tocar tablas de Torneos": agregar esta columna
 * a `clubs` es aditivo y no rompe ninguna lógica existente; el modelo de datos
 * de FutGO Social lo requiere por diseño (ver PROPUESTA_FUTGO_SOCIAL_v3 §3.3).
 */
return new class extends Migration
{
    /** Valores admitidos — espejados en User::PLAY_LEVELS y Club::PLAY_LEVELS. */
    private const LEVELS = ['recreativo', 'intermedio', 'competitivo', 'elite_amateur'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('play_level', 20)->nullable()->after('avatar_url');
            $table->index('play_level');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->string('play_level', 20)->nullable()->after('status');
            $table->index('play_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['play_level']);
            $table->dropColumn('play_level');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex(['play_level']);
            $table->dropColumn('play_level');
        });
    }
};
