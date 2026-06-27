<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · conversations (esquema, sin UI).
 *
 * El dominio de mensajería se DISEÑA ahora para no migrar en caliente en Fase 2.
 * El MVP solo usa mensajes estructurados (los que viajan en OpportunityResponse);
 * el esquema ya soporta mensajería libre sin migraciones adicionales.
 *
 * Una conversación puede vincularse opcionalmente a una Opportunity o
 * FriendlyMatch (morph `subject`, nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Vínculo opcional: 'opportunity' | 'friendly_match' (morph map).
            $table->nullableMorphs('subject');   // subject_type + subject_id

            $table->dateTime('last_message_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
