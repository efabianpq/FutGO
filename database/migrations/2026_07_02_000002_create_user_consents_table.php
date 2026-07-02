<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Prueba de consentimiento (Ley 1581/2012, Decreto 1377/2013).
 *
 * Una fila por cada aceptación (o revocación) de un documento por un usuario, con
 * versión + IP + user_agent, para demostrar legalmente cuándo y desde dónde aceptó.
 * Append-only: nunca se actualiza, cada evento nuevo es una fila nueva.
 *
 * document_type: privacy | terms | cookies | content | minors | marketing | parental
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('document_version', 20)->nullable();
            $table->boolean('accepted')->default(true);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            // register | reconsent | settings | parental
            $table->string('source', 20)->default('register');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
