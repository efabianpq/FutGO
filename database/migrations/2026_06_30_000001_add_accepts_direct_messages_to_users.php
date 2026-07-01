<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H20 — Mensajería directa: flag de privacidad en el perfil del usuario.
 * Cuando accepts_direct_messages = false, nadie puede iniciar nuevas
 * conversaciones directas con ese usuario desde su ficha pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('accepts_direct_messages')->default(true)->after('feed_last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accepts_direct_messages');
        });
    }
};
