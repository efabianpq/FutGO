<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · Opportunity (entidad central de Conexiones).
 *
 * "Alguien publica que necesita algo, y otro responde." Modela en una sola
 * tabla los 4 casos de Fase 1: BUSCAR_RIVAL, BUSCAR_JUGADOR (plantilla
 * permanente), BUSCAR_REFUERZO (puntual) y BUSCAR_EQUIPO (jugador libre).
 *
 * Decisiones de diseño:
 * - `type` y `status` son STRING, no ENUM: agregar nuevos tipos de oportunidad
 *   (BUSCAR_ARBITRO, BUSCAR_CANCHA, … del Marketplace) NO requiere migración,
 *   solo un nuevo valor + su forma de `payload`.
 * - El creador es `user_id` y/o `club_id` según el tipo (BUSCAR_EQUIPO lo
 *   publica un usuario; BUSCAR_RIVAL un club). La regla "al menos uno" se
 *   valida en la capa de aplicación (no hay CHECK portable a SQLite).
 * - `payload` JSON tipado según `type` (posiciones buscadas, cupos, etc.).
 * - `required_level` es el filtro de nivel del matching (no decorativo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();

            // Tipo extensible sin migración: BUSCAR_RIVAL | BUSCAR_JUGADOR |
            // BUSCAR_REFUERZO | BUSCAR_EQUIPO | (futuros del Marketplace).
            $table->string('type', 30);

            // Creador: user y/o club según el tipo (al menos uno, validado en app).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();

            // Ubicación declarada (geolocalización es opcional y llega después).
            $table->string('city', 120);
            $table->string('zone', 120)->nullable();

            // Nivel requerido para el matching (recreativo..elite_amateur).
            $table->string('required_level', 20)->nullable();

            // Ventana de fecha/horario en que aplica la necesidad.
            $table->dateTime('window_start')->nullable();
            $table->dateTime('window_end')->nullable();

            // abierta | en_negociacion | cerrada | vencida | cancelada
            $table->string('status', 20)->default('abierta');

            // Payload tipado según `type` (estructura libre, validada por tipo).
            $table->json('payload')->nullable();

            // Vigencia: pasada esta fecha el cron la marca `vencida`.
            $table->dateTime('expires_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['city', 'required_level']);
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
