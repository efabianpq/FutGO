<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · messages.
 *
 * Mensaje dentro de una conversación. `type` distingue 'structured' (MVP, el
 * único habilitado en Fase 1) de 'free' (mensajería libre de Fase 2): el
 * esquema ya soporta ambos sin migrar. Adjuntos (foto) se difieren a Fase 2 con
 * una tabla `message_attachments` propia — no se modela acá para no anticipar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();

            // Emisor: usuario, opcionalmente en representación de un club.
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_club_id')->nullable()->constrained('clubs')->nullOnDelete();

            $table->text('body');

            // structured (MVP) | free (Fase 2)
            $table->string('type', 20)->default('structured');

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
