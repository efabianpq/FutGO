<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reclamo de perfil (Limitación #2 — la deuda más antigua del proyecto).
 *
 * Un capitán puede anotar a un jugador informal SIN cuenta en FutGO usando solo
 * nombre + documento (club_players.verification_status = 'por_verificar'). Ese
 * jugador acumula historial (stats, partidos, tarjetas) en team_players. Cuando
 * se registra eventualmente, este flujo le permite RECLAMAR ese registro y, tras
 * la aprobación del capitán (o de un admin si el capitán ya no existe), vincular
 * su cuenta y HEREDAR el historial.
 *
 * Reglas:
 *  - El reclamo NUNCA vincula automáticamente: siempre requiere confirmación humana.
 *  - Un mismo club_player no puede tener dos reclamos vivos (pendiente/aprobado):
 *    se garantiza en el servicio (no hay índice parcial portable MySQL/SQLite).
 *  - Un documento no puede quedar vinculado a dos user_id distintos: el registro
 *    pasa a 'registrado' al aprobar, bloqueando cualquier reclamo posterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_claims', function (Blueprint $table) {
            $table->id();
            // Usuario que reclama (ya registrado, con cuenta).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Registro por_verificar que se reclama.
            $table->foreignId('club_player_id')->constrained('club_players')->cascadeOnDelete();
            // Club denormalizado (para escalamiento si el club_player se borra en cascada).
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            // Documento snapshot al momento del reclamo (coincide con el del registro).
            $table->string('document', 40)->nullable();
            // pending → approved | rejected ; escalated cuando no hay capitán.
            $table->enum('status', ['pending', 'escalated', 'approved', 'rejected'])
                  ->default('pending');
            // Quién resolvió (capitán o admin) y cuándo.
            $table->foreignId('resolved_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 255)->nullable();
            $table->timestamps();

            $table->index(['club_player_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_claims');
    }
};
