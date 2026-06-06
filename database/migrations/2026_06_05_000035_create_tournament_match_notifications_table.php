<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de idempotencia de recordatorios de partido del módulo TORNEOS (Sesión G).
 * Mismo patrón que match_notifications de la polla: el unique evita reenviar el
 * mismo recordatorio al mismo jugador para el mismo partido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_match_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->string('type', 30);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'match_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_match_notifications');
    }
};
