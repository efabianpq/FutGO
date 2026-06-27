<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · Sesión S1-E · Seguir entidades y Feed de sistema.
 *
 * `feed_events`: registro ÚNICO por evento del sistema (no una fila por usuario).
 * La relevancia se resuelve en LECTURA combinando:
 *   - `actor` y `subject` (las dos entidades que el evento conecta): se ve si el
 *     usuario sigue a cualquiera de las dos.
 *   - distribución por `city` + `required_level`: se ve si coincide con la ciudad
 *     y nivel del usuario (nivel nulo = para todos).
 * Así un solo registro alcanza a miles de usuarios sin explotar en filas.
 *
 * Además, columnas ADITIVAS a `users`:
 *   - `city`: ciudad del jugador, usada por la distribución del Feed (igual rol
 *     que `required_level` ya cumple con `play_level`).
 *   - `feed_last_read_at`: marca de "leído". El contador de no leídos del navbar
 *     son los eventos relevantes posteriores a esta marca — O(1) en almacenamiento,
 *     sin tabla de lecturas por usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_events', function (Blueprint $table) {
            $table->id();

            // Tipo de evento (STRING extensible sin migración): oportunidad_publicada,
            // oportunidad_aceptada, amistoso_confirmado, resultado_amistoso,
            // resultado_torneo, logro_desbloqueado, ...
            $table->string('type', 40);

            // Las dos entidades que el evento conecta (morph map: user/club/tournament/
            // opportunity/achievement/...). Seguir a cualquiera lo vuelve relevante.
            $table->nullableMorphs('actor');     // actor_type + actor_id
            $table->nullableMorphs('subject');   // subject_type + subject_id

            // Distribución geográfica/nivel (broadcast). Nulo = no se distribuye por
            // ciudad, solo por follows.
            $table->string('city', 120)->nullable();
            $table->string('required_level', 20)->nullable();

            // Datos denormalizados para renderizar la tarjeta del Feed sin joins
            // pesados (nombres, marcador, escudos, enlaces, etc.).
            $table->json('payload')->nullable();

            // Momento del evento (puede diferir del created_at en reconstrucciones).
            $table->timestamp('occurred_at')->useCurrent();

            $table->timestamps();

            // El Feed siempre ordena por fecha desc y filtra por ciudad/nivel.
            $table->index(['occurred_at']);
            $table->index(['city', 'required_level']);
            $table->index(['type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('city', 120)->nullable()->after('play_level');
            $table->timestamp('feed_last_read_at')->nullable()->after('city');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropColumn(['city', 'feed_last_read_at']);
        });

        Schema::dropIfExists('feed_events');
    }
};
