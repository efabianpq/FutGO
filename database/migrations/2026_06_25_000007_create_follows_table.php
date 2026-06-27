<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · follows.
 *
 * UNA sola tabla polimórfica para "seguir": un usuario sigue a un club, a otro
 * jugador o a un torneo (cancha/organizador cuando existan). "Favoritos" no es
 * un concepto aparte — es esta misma acción aplicada a un club. Alimenta el Feed.
 *
 * El par (follower, followable) es único: no se sigue dos veces lo mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();

            // Seguidor: siempre un usuario registrado.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Entidad seguida: 'club' | 'user' | 'tournament' (morph map).
            $table->morphs('followable');   // followable_type + followable_id (+ índice)

            $table->timestamps();

            $table->unique(['user_id', 'followable_type', 'followable_id'], 'follows_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
