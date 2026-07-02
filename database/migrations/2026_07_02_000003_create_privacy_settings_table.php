<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Configuración de privacidad por usuario (1:1 con users).
 *
 * El usuario decide qué se muestra públicamente en su ficha. Defaults conservadores:
 * datos de contacto (email/phone/birthdate) ocultos; datos deportivos visibles.
 * Absorbe el antiguo users.accepts_direct_messages (→ allow_messages).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();

            // Datos de contacto / sensibles — ocultos por defecto
            $table->boolean('show_email')->default(false);
            $table->boolean('show_phone')->default(false);
            $table->boolean('show_birthdate')->default(false);

            // Datos deportivos / de descubrimiento — visibles por defecto
            $table->boolean('show_city')->default(true);
            $table->boolean('show_photo')->default(true);
            $table->boolean('show_stats')->default(true);
            $table->boolean('show_teams')->default(true);
            $table->boolean('show_history')->default(true);

            // Descubribilidad
            $table->boolean('public_profile')->default(true);
            $table->boolean('searchable')->default(true);
            $table->boolean('indexable_by_search_engines')->default(true);

            // nadie | companeros | todos
            $table->string('allow_messages', 20)->default('companeros');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
