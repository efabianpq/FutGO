<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · conversation_participants.
 *
 * Participante de una conversación: un usuario y/o (según el contexto) en
 * representación de un club. `last_read_at` soporta el contador de no leídos
 * que necesitará la UI de Fase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();

            $table->dateTime('last_read_at')->nullable();

            $table->timestamps();

            $table->index('conversation_id');
            $table->unique(['conversation_id', 'user_id', 'club_id'], 'conversation_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
